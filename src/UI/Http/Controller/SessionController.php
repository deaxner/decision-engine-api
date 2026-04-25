<?php

namespace App\UI\Http\Controller;

use App\Application\Decision\AuthContext;
use App\Application\Decision\SessionCommandService;
use App\Application\Decision\SessionReadModelQuery;
use App\Application\Decision\WorkspaceAccess;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\Workspace;
use App\UI\Http\Request\RequestInputMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SessionController extends ApiController
{
    public function __construct(
        private readonly AuthContext $auth,
        private readonly WorkspaceAccess $access,
        private readonly SessionCommandService $commands,
        private readonly SessionReadModelQuery $query,
        private readonly RequestInputMapper $inputs,
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

            return $this->ok($this->query->listForWorkspace($workspace));
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
            $this->access->requireOwner($user, $workspace);

            $session = $this->commands->createSession($user, $workspace, $this->inputs->createSession($this->body($request)));

            return $this->ok($this->query->session($session), 201);
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

            $option = $this->commands->addOption($user, $session, $this->inputs->addOption($this->body($request)));

            return $this->ok($this->query->option($option), 201);
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

            return $this->ok($this->query->session($session));
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

            $session = $this->commands->updateStatus($user, $session, $this->inputs->updateSessionStatus($this->body($request)));

            return $this->ok($this->query->session($session));
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }
}
