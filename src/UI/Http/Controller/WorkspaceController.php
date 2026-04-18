<?php

namespace App\UI\Http\Controller;

use App\Application\Decision\AuthContext;
use App\Application\Decision\WorkspaceAccess;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class WorkspaceController extends ApiController
{
    #[Route('/workspaces', methods: ['POST'])]
    public function create(Request $request, AuthContext $auth, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $auth->user($request);
            $body = $this->body($request);
            foreach (['name', 'slug'] as $field) {
                if (!isset($body[$field]) || !is_string($body[$field]) || trim($body[$field]) === '') {
                    throw new \DomainException(sprintf('Missing required field: %s.', $field));
                }
            }

            $workspace = new Workspace($body['name'], $body['slug'], $user);
            $entityManager->persist($workspace);
            $entityManager->persist(new WorkspaceMember($workspace, $user, WorkspaceMember::OWNER));
            $entityManager->flush();

            return $this->ok($this->workspacePayload($workspace), 201);
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }

    #[Route('/workspaces/{id}/members', methods: ['POST'])]
    public function addMember(int $id, Request $request, AuthContext $auth, WorkspaceAccess $access, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $auth->user($request);
            $workspace = $entityManager->find(Workspace::class, $id);
            if (!$workspace instanceof Workspace) {
                throw new \DomainException('Workspace not found.');
            }
            $access->requireOwner($user, $workspace);

            $body = $this->body($request);
            $memberUser = isset($body['user_id']) ? $entityManager->find(User::class, (int) $body['user_id']) : null;
            if (!$memberUser instanceof User) {
                throw new \DomainException('Member user not found.');
            }

            $entityManager->persist(new WorkspaceMember($workspace, $memberUser, WorkspaceMember::MEMBER));
            $entityManager->flush();

            return $this->ok(['workspace_id' => (string) $id, 'user_id' => (string) $memberUser->getId(), 'role' => WorkspaceMember::MEMBER], 201);
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }

    private function workspacePayload(Workspace $workspace): array
    {
        return [
            'id' => (string) $workspace->getId(),
            'name' => $workspace->getName(),
            'slug' => $workspace->getSlug(),
        ];
    }
}

