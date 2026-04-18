<?php

namespace App\UI\Http\Controller;

use App\Application\Decision\AuthContext;
use App\Application\Decision\ResultCalculator;
use App\Application\Decision\VotePayloadValidator;
use App\Application\Decision\WorkspaceAccess;
use App\Domain\Decision\Entity\DecisionOption;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\Vote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class VoteController extends ApiController
{
    #[Route('/sessions/{id}/votes', methods: ['POST'])]
    public function cast(int $id, Request $request, AuthContext $auth, WorkspaceAccess $access, VotePayloadValidator $validator, ResultCalculator $results, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $auth->user($request);
            $session = $entityManager->find(DecisionSession::class, $id);
            if (!$session instanceof DecisionSession) {
                throw new \DomainException('Session not found.');
            }
            $access->requireMember($user, $session->getWorkspace());
            if ($session->getStatus() !== DecisionSession::OPEN) {
                throw new \DomainException('Votes can only be cast while the session is open.');
            }

            $payload = $this->body($request);
            $optionIds = array_values(array_filter(array_map(fn (DecisionOption $option) => $option->getId(), $session->getOptions()->toArray())));
            $validator->validate($session->getVotingType(), $payload, $optionIds);

            $vote = new Vote($session, $user, $payload);
            $entityManager->persist($vote);
            $entityManager->flush();
            $result = $results->recompute($session);

            return $this->ok(['vote_id' => (string) $vote->getId(), 'result' => $result->toArray()], 202);
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }

    #[Route('/sessions/{id}/results', methods: ['GET'])]
    public function result(int $id, Request $request, AuthContext $auth, WorkspaceAccess $access, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $auth->user($request);
            $session = $entityManager->find(DecisionSession::class, $id);
            if (!$session instanceof DecisionSession) {
                throw new \DomainException('Session not found.');
            }
            $access->requireMember($user, $session->getWorkspace());
            if ($session->getStatus() === DecisionSession::DRAFT) {
                return $this->ok(['result' => null], 204);
            }

            $result = $entityManager->getRepository(SessionResult::class)->find($session);
            if (!$result instanceof SessionResult) {
                return $this->ok(['result' => null], 204);
            }

            return $this->ok($result->toArray());
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }
}

