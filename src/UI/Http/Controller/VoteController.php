<?php

namespace App\UI\Http\Controller;

use App\Application\Decision\AuthContext;
use App\Application\Decision\ActivityRecorder;
use App\Application\Decision\Message\VoteCastEvent;
use App\Application\Decision\VotePayloadValidator;
use App\Application\Decision\WorkspaceAccess;
use App\Domain\Decision\Entity\DecisionOption;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\Vote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class VoteController extends ApiController
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    #[Route('/sessions/{id}/votes', methods: ['POST'])]
    public function cast(int $id, Request $request, AuthContext $auth, WorkspaceAccess $access, VotePayloadValidator $validator, ActivityRecorder $activity, EntityManagerInterface $entityManager): JsonResponse
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
            $existingVote = $entityManager->getRepository(Vote::class)->findOneBy(['session' => $session, 'user' => $user]);
            $activityType = $existingVote instanceof Vote ? 'vote_changed' : 'vote_cast';

            $vote = new Vote($session, $user, $payload);
            $entityManager->persist($vote);
            $activity->record(
                $session->getWorkspace(),
                $activityType,
                sprintf('%s %s vote on %s.', $user->getDisplayName(), $activityType === 'vote_changed' ? 'changed their' : 'cast a', $session->getTitle()),
                $user,
                $session,
            );
            $entityManager->flush();
            $this->bus->dispatch(new VoteCastEvent((int) $session->getId(), (int) $vote->getId()));

            return $this->ok([
                'vote_id' => (string) $vote->getId(),
                'session_id' => (string) $session->getId(),
                'status' => 'accepted',
            ], 202);
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
