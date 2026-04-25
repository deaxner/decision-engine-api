<?php

namespace App\UI\Http\Controller;

use App\Application\Decision\AuthContext;
use App\Application\Decision\SessionReadModelQuery;
use App\Application\Decision\VoteCommandService;
use App\Application\Decision\WorkspaceAccess;
use App\Domain\Decision\Entity\DecisionSession;
use App\UI\Http\Request\RequestInputMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class VoteController extends ApiController
{
    public function __construct(
        private readonly VoteCommandService $commands,
        private readonly RequestInputMapper $inputs,
        private readonly SessionReadModelQuery $query,
    )
    {
    }

    #[Route('/sessions/{id}/votes', methods: ['POST'])]
    public function cast(int $id, Request $request, AuthContext $auth, WorkspaceAccess $access, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $auth->user($request);
            $session = $entityManager->find(DecisionSession::class, $id);
            if (!$session instanceof DecisionSession) {
                throw new \DomainException('Session not found.');
            }
            $access->requireMember($user, $session->getWorkspace());

            $vote = $this->commands->castVote($user, $session, $this->inputs->castVote($this->body($request)));

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

            $result = $this->query->result($session);
            if ($result === null) {
                return $this->ok(['result' => null], 204);
            }

            return $this->ok($result);
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }
}
