<?php

namespace App\UI\Http\Controller;

use App\Application\Decision\AuthContext;
use App\Application\Decision\WorkspaceAccess;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class WorkspaceController extends ApiController
{
    public function __construct(
        private readonly AuthContext $auth,
        private readonly WorkspaceAccess $access,
    ) {
    }

    #[Route('/workspaces', methods: ['GET'])]
    public function listWorkspaces(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $this->auth->user($request);
            $memberships = $entityManager->getRepository(WorkspaceMember::class)->findBy(['user' => $user]);

            return $this->ok(array_map(
                fn (WorkspaceMember $member) => $this->workspacePayload($entityManager, $member->getWorkspace(), $member->getRole()),
                $memberships,
            ));
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }

    #[Route('/workspaces', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $this->auth->user($request);
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

            return $this->ok($this->workspacePayload($entityManager, $workspace, WorkspaceMember::OWNER), 201);
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

            return $this->ok($this->workspacePayload($entityManager, $workspace, $member->getRole()));
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

            $body = $this->body($request);
            $memberUser = null;
            if (isset($body['email']) && is_string($body['email'])) {
                $memberUser = $entityManager->getRepository(User::class)->findOneBy(['email' => strtolower($body['email'])]);
            } elseif (isset($body['user_id'])) {
                $memberUser = $entityManager->find(User::class, (int) $body['user_id']);
            }
            if (!$memberUser instanceof User) {
                throw new \DomainException('Member user not found.');
            }
            if ($entityManager->getRepository(WorkspaceMember::class)->findOneBy(['workspace' => $workspace, 'user' => $memberUser])) {
                throw new \DomainException('User is already a workspace member.');
            }

            $entityManager->persist(new WorkspaceMember($workspace, $memberUser, WorkspaceMember::MEMBER));
            $entityManager->flush();

            return $this->ok(['workspace_id' => (string) $id, 'user_id' => (string) $memberUser->getId(), 'role' => WorkspaceMember::MEMBER], 201);
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }

    private function workspacePayload(EntityManagerInterface $entityManager, Workspace $workspace, ?string $role = null): array
    {
        $memberCount = $entityManager->getRepository(WorkspaceMember::class)->count(['workspace' => $workspace]);
        $sessions = $entityManager->getRepository(DecisionSession::class)->findBy(['workspace' => $workspace]);
        $draftCount = 0;
        $openCount = 0;
        $closedCount = 0;
        $participationRates = [];

        foreach ($sessions as $session) {
            if (!$session instanceof DecisionSession) {
                continue;
            }

            if ($session->getStatus() === DecisionSession::DRAFT) {
                ++$draftCount;
                continue;
            }

            if ($session->getStatus() === DecisionSession::OPEN) {
                ++$openCount;
            } elseif ($session->getStatus() === DecisionSession::CLOSED) {
                ++$closedCount;
            }

            $result = $entityManager->getRepository(SessionResult::class)->find($session);
            if ($result instanceof SessionResult && $memberCount > 0) {
                $resultData = $result->toArray()['result_data'] ?? [];
                $totalVotes = isset($resultData['total_votes']) ? (int) $resultData['total_votes'] : 0;
                $participationRates[] = min(100, (int) round(($totalVotes / $memberCount) * 100));
            }
        }

        $payload = [
            'id' => (string) $workspace->getId(),
            'name' => $workspace->getName(),
            'slug' => $workspace->getSlug(),
            'member_count' => $memberCount,
            'participation_rate' => count($participationRates) > 0 ? (int) round(array_sum($participationRates) / count($participationRates)) : 0,
            'session_counts' => [
                'total' => count($sessions),
                'draft' => $draftCount,
                'open' => $openCount,
                'closed' => $closedCount,
            ],
        ];

        if ($role !== null) {
            $payload['role'] = $role;
        }

        return $payload;
    }
}
