<?php

namespace App\UI\Http\Controller;

use App\Application\Decision\AuthContext;
use App\Application\Decision\WorkspaceCommandService;
use App\Application\Decision\WorkspaceAccess;
use App\Application\Decision\WorkspaceReadModelQuery;
use App\Domain\Decision\Entity\Workspace;
use App\UI\Http\Request\RequestInputMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class WorkspaceController extends ApiController
{
    public function __construct(
        private readonly AuthContext $auth,
        private readonly WorkspaceAccess $access,
        private readonly WorkspaceCommandService $commands,
        private readonly WorkspaceReadModelQuery $query,
        private readonly RequestInputMapper $inputs,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/workspaces', methods: ['GET'])]
    public function listWorkspaces(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $this->auth->user($request);
            return $this->ok($this->query->listForUser($user));
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }

    #[Route('/workspaces', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $this->auth->user($request);
            $workspace = $this->commands->createWorkspace($user, $this->inputs->createWorkspace($this->body($request)));

            return $this->ok($this->query->workspace($workspace, 'OWNER'), 201);
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }

    #[Route('/workspaces/{id}', methods: ['GET'])]
    public function detail(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $this->auth->user($request);
            $workspace = $entityManager->find(Workspace::class, $id);
            if (!$workspace instanceof Workspace) {
                throw new \DomainException('Workspace not found.');
            }
            $member = $this->access->requireMember($user, $workspace);

            return $this->ok($this->query->workspace($workspace, $member->getRole()));
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }

    #[Route('/workspaces/{id}/members', methods: ['GET'])]
    public function listMembers(int $id, Request $request): JsonResponse
    {
        try {
            $user = $this->auth->user($request);
            $workspace = $this->entityManager->find(Workspace::class, $id);
            if (!$workspace instanceof Workspace) {
                throw new \DomainException('Workspace not found.');
            }
            $this->access->requireMember($user, $workspace);

            return $this->ok($this->query->members($workspace));
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }

    #[Route('/workspaces/{id}/members', methods: ['POST'])]
    public function addMember(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $this->auth->user($request);
            $workspace = $entityManager->find(Workspace::class, $id);
            if (!$workspace instanceof Workspace) {
                throw new \DomainException('Workspace not found.');
            }
            $this->access->requireOwner($user, $workspace);

            $member = $this->commands->addMember($user, $workspace, $this->inputs->addWorkspaceMember($this->body($request)));

            return $this->ok(['workspace_id' => (string) $id, 'user_id' => (string) $member->getUser()->getId(), 'role' => 'MEMBER'], 201);
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }
}
