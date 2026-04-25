<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\DecisionOption;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;

interface SessionCommandRepository
{
    public function addSession(DecisionSession $session): void;

    public function addOption(DecisionOption $option): void;

    public function findUserById(int $id): ?User;

    public function hasWorkspaceMembership(Workspace $workspace, User $user): bool;

    public function flush(): void;
}
