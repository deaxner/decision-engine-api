<?php

namespace App\UI\Http\Controller;

use App\Application\Decision\AuthContext;
use App\Application\Decision\Message\RecomputeSessionResult;
use App\Application\Decision\WorkspaceAccess;
use App\Domain\Decision\Entity\DecisionOption;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class SessionController extends ApiController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly AuthContext $auth,
        private readonly WorkspaceAccess $access,
    ) {
    }

    #[Route('/workspaces/{id}/sessions', methods: ['GET'])]
    public function listForWorkspace(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $this->auth->user($request);
            $workspace = $entityManager->find(Workspace::class, $id);
            if (!$workspace instanceof Workspace) {
                throw new \DomainException('Workspace not found.');
            }
            $this->access->requireMember($user, $workspace);

            $sessions = $entityManager->getRepository(DecisionSession::class)->findBy(['workspace' => $workspace], ['createdAt' => 'DESC']);

            return $this->ok(array_map(fn (DecisionSession $session) => $this->sessionPayload($session), $sessions));
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }

    #[Route('/workspaces/{id}/sessions', methods: ['POST'])]
    public function create(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $this->auth->user($request);
            $workspace = $entityManager->find(Workspace::class, $id);
            if (!$workspace instanceof Workspace) {
                throw new \DomainException('Workspace not found.');
            }
            $this->access->requireMember($user, $workspace);

            $body = $this->body($request);
            foreach (['title', 'voting_type'] as $field) {
                if (!isset($body[$field]) || !is_string($body[$field]) || trim($body[$field]) === '') {
                    throw new \DomainException(sprintf('Missing required field: %s.', $field));
                }
            }

            $session = new DecisionSession($workspace, $user, $body['title'], $body['description'] ?? null, $body['voting_type']);
            $entityManager->persist($session);
            $entityManager->flush();

            return $this->ok($this->sessionPayload($session), 201);
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }

    #[Route('/sessions/{id}/options', methods: ['POST'])]
    public function addOption(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $this->auth->user($request);
            $session = $entityManager->find(DecisionSession::class, $id);
            if (!$session instanceof DecisionSession) {
                throw new \DomainException('Session not found.');
            }
            $this->access->requireOwner($user, $session->getWorkspace());

            $body = $this->body($request);
            if (!isset($body['title']) || !is_string($body['title']) || trim($body['title']) === '') {
                throw new \DomainException('Missing required field: title.');
            }

            $position = isset($body['position']) ? (int) $body['position'] : $session->getOptions()->count() + 1;
            $option = new DecisionOption($session, $body['title'], $position);
            $session->addOption($option);
            $entityManager->persist($option);
            $entityManager->flush();

            return $this->ok($this->optionPayload($option), 201);
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }

    #[Route('/sessions/{id}', methods: ['GET'])]
    public function detail(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $this->auth->user($request);
            $session = $entityManager->find(DecisionSession::class, $id);
            if (!$session instanceof DecisionSession) {
                throw new \DomainException('Session not found.');
            }
            $this->access->requireMember($user, $session->getWorkspace());

            return $this->ok($this->sessionPayload($session));
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }

    #[Route('/sessions/{id}', methods: ['PATCH'])]
    public function updateStatus(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $this->auth->user($request);
            $session = $entityManager->find(DecisionSession::class, $id);
            if (!$session instanceof DecisionSession) {
                throw new \DomainException('Session not found.');
            }
            $this->access->requireOwner($user, $session->getWorkspace());

            $body = $this->body($request);
            $status = $body['status'] ?? null;
            if ($status === DecisionSession::OPEN) {
                $session->open();
            } elseif ($status === DecisionSession::CLOSED) {
                $session->close();
            } else {
                throw new \DomainException('Unsupported session status transition.');
            }

            $entityManager->flush();
            if ($status === DecisionSession::CLOSED) {
                $this->bus->dispatch(new RecomputeSessionResult((int) $session->getId(), 'session_closed'));
            }

            return $this->ok($this->sessionPayload($session));
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }

    private function sessionPayload(DecisionSession $session): array
    {
        return [
            'id' => (string) $session->getId(),
            'title' => $session->getTitle(),
            'description' => $session->getDescription(),
            'status' => $session->getStatus(),
            'voting_type' => $session->getVotingType(),
            'starts_at' => $session->getStartsAt()?->format(\DateTimeInterface::ATOM),
            'ends_at' => $session->getEndsAt()?->format(\DateTimeInterface::ATOM),
            'options' => array_map(fn (DecisionOption $option) => $this->optionPayload($option), $session->getOptions()->toArray()),
        ];
    }

    private function optionPayload(DecisionOption $option): array
    {
        return [
            'id' => (string) $option->getId(),
            'title' => $option->getTitle(),
            'position' => $option->getPosition(),
        ];
    }
}
