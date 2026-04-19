<?php

namespace App\UI\Http\Controller;

use App\Application\Decision\AuthContext;
use App\Application\Decision\WorkspaceAccess;
use App\Domain\Decision\Entity\ActivityEvent;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\Vote;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class WorkspaceDashboardController extends ApiController
{
    public function __construct(
        private readonly AuthContext $auth,
        private readonly WorkspaceAccess $access,
    ) {
    }

    #[Route('/workspaces/{id}/dashboard', methods: ['GET'])]
    public function dashboard(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $this->auth->user($request);
            $workspace = $entityManager->find(Workspace::class, $id);
            if (!$workspace instanceof Workspace) {
                throw new \DomainException('Workspace not found.');
            }
            $member = $this->access->requireMember($user, $workspace);
            $workspacePayload = $this->workspacePayload($entityManager, $workspace, $member->getRole());
            $metrics = $this->metricsPayload($entityManager, $workspace, $workspacePayload);
            $activity = $entityManager->getRepository(ActivityEvent::class)->findBy(['workspace' => $workspace], ['createdAt' => 'DESC'], 12);

            return $this->ok([
                'workspace' => $workspacePayload,
                'metrics' => $metrics,
                'activity' => array_map(fn (ActivityEvent $event) => $this->activityPayload($event), $activity),
                'insights' => $this->insightsPayload($workspacePayload, $metrics),
            ]);
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }

    private function workspacePayload(EntityManagerInterface $entityManager, Workspace $workspace, string $role): array
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

        return [
            'id' => (string) $workspace->getId(),
            'name' => $workspace->getName(),
            'slug' => $workspace->getSlug(),
            'role' => $role,
            'member_count' => $memberCount,
            'participation_rate' => count($participationRates) > 0 ? (int) round(array_sum($participationRates) / count($participationRates)) : 0,
            'session_counts' => [
                'total' => count($sessions),
                'draft' => $draftCount,
                'open' => $openCount,
                'closed' => $closedCount,
            ],
        ];
    }

    private function metricsPayload(EntityManagerInterface $entityManager, Workspace $workspace, array $workspacePayload): array
    {
        $closedSessions = $entityManager->getRepository(DecisionSession::class)->findBy(['workspace' => $workspace, 'status' => DecisionSession::CLOSED]);
        $durations = [];
        foreach ($closedSessions as $session) {
            if (!$session instanceof DecisionSession || $session->getStartsAt() === null || $session->getEndsAt() === null) {
                continue;
            }
            $durations[] = max(0, $session->getEndsAt()->getTimestamp() - $session->getStartsAt()->getTimestamp()) / 86400;
        }

        $distinctVoters = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT IDENTITY(v.user))')
            ->from(Vote::class, 'v')
            ->join('v.session', 's')
            ->where('s.workspace = :workspace')
            ->andWhere('s.status IN (:statuses)')
            ->setParameter('workspace', $workspace)
            ->setParameter('statuses', [DecisionSession::OPEN, DecisionSession::CLOSED])
            ->getQuery()
            ->getSingleScalarResult();

        $memberCount = (int) $workspacePayload['member_count'];
        $counts = $workspacePayload['session_counts'];

        return [
            'decision_speed_days' => count($durations) > 0 ? round(array_sum($durations) / count($durations), 1) : null,
            'engagement_rate' => $memberCount > 0 ? min(100, (int) round(($distinctVoters / $memberCount) * 100)) : 0,
            'active_session_count' => $counts['open'],
            'draft_session_count' => $counts['draft'],
            'closed_session_count' => $counts['closed'],
        ];
    }

    private function activityPayload(ActivityEvent $event): array
    {
        $actor = $event->getActor();
        $session = $event->getSession();

        return [
            'id' => (string) $event->getId(),
            'type' => $event->getType(),
            'summary' => $event->getSummary(),
            'actor' => $actor ? [
                'id' => (string) $actor->getId(),
                'display_name' => $actor->getDisplayName(),
            ] : null,
            'workspace_id' => (string) $event->getWorkspace()->getId(),
            'session_id' => $session ? (string) $session->getId() : null,
            'session_title' => $session?->getTitle(),
            'created_at' => $event->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'metadata' => $event->getMetadata(),
        ];
    }

    private function insightsPayload(array $workspace, array $metrics): array
    {
        $counts = $workspace['session_counts'];
        $insights = [];

        if ($metrics['engagement_rate'] < 50 && $counts['open'] + $counts['closed'] > 0) {
            $insights[] = [
                'id' => 'low-participation',
                'kind' => 'participation',
                'severity' => 'warning',
                'title' => 'Participation is below target',
                'body' => sprintf('%d%% of workspace members have participated in current decisions.', $metrics['engagement_rate']),
                'session_id' => null,
            ];
        }

        if ($counts['draft'] > $counts['open']) {
            $insights[] = [
                'id' => 'draft-backlog',
                'kind' => 'drafts',
                'severity' => 'warning',
                'title' => 'Draft queue is growing',
                'body' => sprintf('%d draft decisions still need options or voting to open.', $counts['draft']),
                'session_id' => null,
            ];
        }

        if ($counts['open'] === 0) {
            $insights[] = [
                'id' => 'no-active-sessions',
                'kind' => 'activity',
                'severity' => 'info',
                'title' => 'No active voting sessions',
                'body' => 'Open a draft when options are ready to start collecting votes.',
                'session_id' => null,
            ];
        }

        if ($counts['closed'] > 0) {
            $insights[] = [
                'id' => 'decision-record-growing',
                'kind' => 'archive',
                'severity' => 'success',
                'title' => 'Decision record is growing',
                'body' => sprintf('%d closed decisions are retained as accountable history.', $counts['closed']),
                'session_id' => null,
            ];
        }

        return $insights;
    }
}
