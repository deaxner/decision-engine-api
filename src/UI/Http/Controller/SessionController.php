<?php

namespace App\UI\Http\Controller;

use App\Application\Decision\AuthContext;
use App\Application\Decision\ActivityRecorder;
use App\Application\Decision\Message\RecomputeSessionResult;
use App\Application\Decision\WorkspaceAccess;
use App\Domain\Decision\Entity\DecisionOption;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionAssignee;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;
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
        private readonly ActivityRecorder $activity,
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
            $this->access->requireOwner($user, $workspace);

            $body = $this->body($request);
            foreach (['title', 'voting_type'] as $field) {
                if (!isset($body[$field]) || !is_string($body[$field]) || trim($body[$field]) === '') {
                    throw new \DomainException(sprintf('Missing required field: %s.', $field));
                }
            }

            $dueAt = null;
            if (isset($body['due_at']) && is_string($body['due_at']) && trim($body['due_at']) !== '') {
                $dueAt = new \DateTimeImmutable($body['due_at']);
            }

            $category = isset($body['category']) && is_string($body['category']) ? $body['category'] : null;
            $session = new DecisionSession($workspace, $user, $body['title'], $body['description'] ?? null, $body['voting_type'], $category, $dueAt);
            foreach ($this->assigneesFromBody($body, $workspace, $entityManager) as $assignee) {
                $session->assign($assignee);
            }
            $entityManager->persist($session);
            $this->activity->record(
                $workspace,
                'session_created',
                sprintf('%s created decision %s.', $user->getDisplayName(), $session->getTitle()),
                $user,
                $session,
                [
                    'category' => $session->getCategory(),
                    'due_at' => $session->getDueAt()?->format(\DateTimeInterface::ATOM),
                    'assignee_ids' => array_map(fn (SessionAssignee $assignee) => (string) $assignee->getUser()->getId(), $session->getAssignees()->toArray()),
                ],
            );
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
            $this->activity->record(
                $session->getWorkspace(),
                'option_added',
                sprintf('%s added option %s to %s.', $user->getDisplayName(), $option->getTitle(), $session->getTitle()),
                $user,
                $session,
                ['option_title' => $option->getTitle()],
            );
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
                $this->activity->record(
                    $session->getWorkspace(),
                    'voting_opened',
                    sprintf('%s opened voting for %s.', $user->getDisplayName(), $session->getTitle()),
                    $user,
                    $session,
                );
            } elseif ($status === DecisionSession::CLOSED) {
                $session->close();
                $this->activity->record(
                    $session->getWorkspace(),
                    'session_closed',
                    sprintf('%s closed %s.', $user->getDisplayName(), $session->getTitle()),
                    $user,
                    $session,
                );
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
            'category' => $session->getCategory(),
            'status' => $session->getStatus(),
            'voting_type' => $session->getVotingType(),
            'due_at' => $session->getDueAt()?->format(\DateTimeInterface::ATOM),
            'starts_at' => $session->getStartsAt()?->format(\DateTimeInterface::ATOM),
            'ends_at' => $session->getEndsAt()?->format(\DateTimeInterface::ATOM),
            'assignees' => array_map(fn (SessionAssignee $assignee) => $this->assigneePayload($assignee), $session->getAssignees()->toArray()),
            'options' => array_map(fn (DecisionOption $option) => $this->optionPayload($option), $session->getOptions()->toArray()),
        ];
    }

    /**
     * @return list<User>
     */
    private function assigneesFromBody(array $body, Workspace $workspace, EntityManagerInterface $entityManager): array
    {
        $assigneeIds = $body['assignee_ids'] ?? [];
        if ($assigneeIds === null || $assigneeIds === '') {
            return [];
        }
        if (!is_array($assigneeIds)) {
            throw new \DomainException('assignee_ids must be an array.');
        }

        $assignees = [];
        foreach (array_unique(array_map('strval', $assigneeIds)) as $assigneeId) {
            if (!is_numeric($assigneeId)) {
                throw new \DomainException('Assignee id must be numeric.');
            }
            $assignee = $entityManager->find(User::class, (int) $assigneeId);
            if (!$assignee instanceof User) {
                throw new \DomainException('Assignee user not found.');
            }
            if (!$entityManager->getRepository(WorkspaceMember::class)->findOneBy(['workspace' => $workspace, 'user' => $assignee])) {
                throw new \DomainException('Assignee must be a workspace member.');
            }
            $assignees[] = $assignee;
        }

        return $assignees;
    }

    private function assigneePayload(SessionAssignee $assignee): array
    {
        $user = $assignee->getUser();

        return [
            'id' => (string) $user->getId(),
            'display_name' => $user->getDisplayName(),
            'email' => $user->getEmail(),
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
