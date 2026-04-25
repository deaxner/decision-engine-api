<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\Workspace;

interface SessionReadRepository
{
    /**
     * @return list<DecisionSession>
     */
    public function sessionsForWorkspace(Workspace $workspace): array;

    public function resultForSession(DecisionSession $session): ?SessionResult;
}
