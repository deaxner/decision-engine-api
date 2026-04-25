<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\ActivityEvent;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Vote;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;

interface WorkspaceReadRepository
{
    /**
     * @return list<WorkspaceMember>
     */
    public function membershipsForUser(User $user): array;

    public function memberCount(Workspace $workspace): int;

    /**
     * @return list<DecisionSession>
     */
    public function sessionsForWorkspace(Workspace $workspace, ?string $status = null, array $orderBy = []): array;

    public function resultForSession(DecisionSession $session): ?SessionResult;

    /**
     * @return list<WorkspaceMember>
     */
    public function membersForWorkspace(Workspace $workspace): array;

    /**
     * @return list<ActivityEvent>
     */
    public function recentActivityForWorkspace(Workspace $workspace, int $limit): array;

    /**
     * @param list<string> $statuses
     */
    public function distinctVoterCountForWorkspace(Workspace $workspace, array $statuses): int;
}
