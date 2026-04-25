<?php

namespace App\Application\Decision;

use App\Application\Decision\Output\ActivityEventOutput;
use App\Application\Decision\Output\WorkspaceDashboardOutput;
use App\Application\Decision\Output\WorkspaceInsightOutput;
use App\Application\Decision\Output\WorkspaceMemberOutput;
use App\Application\Decision\Output\WorkspaceMetricsOutput;
use App\Application\Decision\Output\WorkspaceOutput;
use App\Domain\Decision\Entity\ActivityEvent;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;

final class WorkspaceReadModelQuery
{
    public function __construct(
        private readonly WorkspaceReadRepository $repository,
    ) {
    }

    /**
     * @return list<WorkspaceOutput>
     */
    public function listForUser(User $user): array
    {
        $memberships = $this->repository->membershipsForUser($user);

        return array_map(
            fn (WorkspaceMember $member) => $this->workspace($member->getWorkspace(), $member->getRole()),
            $memberships,
        );
    }

    public function workspace(Workspace $workspace, ?string $role = null): WorkspaceOutput
    {
        $memberCount = $this->repository->memberCount($workspace);
        $sessions = $this->repository->sessionsForWorkspace($workspace);
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

            $result = $this->repository->resultForSession($session);
            if ($result instanceof SessionResult && $memberCount > 0) {
                $resultData = $result->toArray()['result_data'] ?? [];
                $totalVotes = isset($resultData['total_votes']) ? (int) $resultData['total_votes'] : 0;
                $participationRates[] = min(100, (int) round(($totalVotes / $memberCount) * 100));
            }
        }

        return new WorkspaceOutput(
            id: (string) $workspace->getId(),
            name: $workspace->getName(),
            slug: $workspace->getSlug(),
            memberCount: $memberCount,
            participationRate: count($participationRates) > 0 ? (int) round(array_sum($participationRates) / count($participationRates)) : 0,
            sessionCounts: [
                'total' => count($sessions),
                'draft' => $draftCount,
                'open' => $openCount,
                'closed' => $closedCount,
            ],
            role: $role,
        );
    }

    /**
     * @return list<WorkspaceMemberOutput>
     */
    public function members(Workspace $workspace): array
    {
        $members = $this->repository->membersForWorkspace($workspace);

        return array_map(fn (WorkspaceMember $member) => $this->member($member), $members);
    }

    public function dashboard(Workspace $workspace, string $role): WorkspaceDashboardOutput
    {
        $workspacePayload = $this->workspace($workspace, $role);
        $metrics = $this->metrics($workspace, $workspacePayload);
        $activity = $this->repository->recentActivityForWorkspace($workspace, 12);

        return new WorkspaceDashboardOutput(
            workspace: $workspacePayload,
            metrics: $metrics,
            activity: array_map(fn (ActivityEvent $event) => $this->activity($event), $activity),
            insights: $this->insights($workspace, $workspacePayload, $metrics),
        );
    }

    private function member(WorkspaceMember $member): WorkspaceMemberOutput
    {
        $user = $member->getUser();

        return new WorkspaceMemberOutput(
            id: (string) $user->getId(),
            email: $user->getEmail(),
            displayName: $user->getDisplayName(),
            role: $member->getRole(),
        );
    }

    private function metrics(Workspace $workspace, WorkspaceOutput $workspacePayload): WorkspaceMetricsOutput
    {
        $closedSessions = $this->repository->sessionsForWorkspace($workspace, DecisionSession::CLOSED);
        $durations = [];
        foreach ($closedSessions as $session) {
            if (!$session instanceof DecisionSession || $session->getStartsAt() === null || $session->getEndsAt() === null) {
                continue;
            }
            $durations[] = max(0, $session->getEndsAt()->getTimestamp() - $session->getStartsAt()->getTimestamp()) / 86400;
        }

        $distinctVoters = $this->repository->distinctVoterCountForWorkspace($workspace, [DecisionSession::OPEN, DecisionSession::CLOSED]);

        $memberCount = $workspacePayload->memberCount;
        $counts = $workspacePayload->sessionCounts;

        return new WorkspaceMetricsOutput(
            decisionSpeedDays: count($durations) > 0 ? round(array_sum($durations) / count($durations), 1) : null,
            engagementRate: $memberCount > 0 ? min(100, (int) round(($distinctVoters / $memberCount) * 100)) : 0,
            activeSessionCount: $counts['open'],
            draftSessionCount: $counts['draft'],
            closedSessionCount: $counts['closed'],
        );
    }

    private function activity(ActivityEvent $event): ActivityEventOutput
    {
        $actor = $event->getActor();
        $session = $event->getSession();

        return new ActivityEventOutput(
            id: (string) $event->getId(),
            type: $event->getType(),
            summary: $event->getSummary(),
            actor: $actor ? [
                'id' => (string) $actor->getId(),
                'display_name' => $actor->getDisplayName(),
            ] : null,
            workspaceId: (string) $event->getWorkspace()->getId(),
            sessionId: $session ? (string) $session->getId() : null,
            sessionTitle: $session?->getTitle(),
            createdAt: $event->getCreatedAt()->format(\DateTimeInterface::ATOM),
            metadata: $event->getMetadata(),
        );
    }

    /**
     * @return list<WorkspaceInsightOutput>
     */
    private function insights(Workspace $workspace, WorkspaceOutput $workspacePayload, WorkspaceMetricsOutput $metrics): array
    {
        $counts = $workspacePayload->sessionCounts;
        $insights = [];
        $now = new \DateTimeImmutable();
        $openSessions = $this->repository->sessionsForWorkspace($workspace, DecisionSession::OPEN);
        foreach ($openSessions as $session) {
            if (!$session instanceof DecisionSession || $session->getDueAt() === null) {
                continue;
            }

            $daysUntilDue = (int) floor(($session->getDueAt()->getTimestamp() - $now->getTimestamp()) / 86400);
            if ($session->getDueAt() < $now) {
                $insights[] = new WorkspaceInsightOutput(
                    id: sprintf('overdue-%s', $session->getId()),
                    kind: 'deadline',
                    severity: 'warning',
                    title: 'Decision is overdue',
                    body: sprintf('%s passed its target deadline.', $session->getTitle()),
                    sessionId: (string) $session->getId(),
                );
            } elseif ($daysUntilDue <= 2) {
                $insights[] = new WorkspaceInsightOutput(
                    id: sprintf('due-soon-%s', $session->getId()),
                    kind: 'deadline',
                    severity: 'info',
                    title: 'Decision deadline is near',
                    body: sprintf('%s is due within %d day%s.', $session->getTitle(), max(0, $daysUntilDue), $daysUntilDue === 1 ? '' : 's'),
                    sessionId: (string) $session->getId(),
                );
            }
        }

        if ($metrics->engagementRate < 50 && $counts['open'] + $counts['closed'] > 0) {
            $insights[] = new WorkspaceInsightOutput(
                id: 'low-participation',
                kind: 'participation',
                severity: 'warning',
                title: 'Participation is below target',
                body: sprintf('%d%% of workspace members have participated in current decisions.', $metrics->engagementRate),
                sessionId: null,
            );
        }

        if ($counts['draft'] > $counts['open']) {
            $insights[] = new WorkspaceInsightOutput(
                id: 'draft-backlog',
                kind: 'drafts',
                severity: 'warning',
                title: 'Draft queue is growing',
                body: sprintf('%d draft decisions still need options or voting to open.', $counts['draft']),
                sessionId: null,
            );
        }

        if ($counts['open'] === 0) {
            $insights[] = new WorkspaceInsightOutput(
                id: 'no-active-sessions',
                kind: 'activity',
                severity: 'info',
                title: 'No active voting sessions',
                body: 'Open a draft when options are ready to start collecting votes.',
                sessionId: null,
            );
        }

        if ($counts['closed'] > 0) {
            $insights[] = new WorkspaceInsightOutput(
                id: 'decision-record-growing',
                kind: 'archive',
                severity: 'success',
                title: 'Decision record is growing',
                body: sprintf('%d closed decisions are retained as accountable history.', $counts['closed']),
                sessionId: null,
            );
        }

        return $insights;
    }
}
