<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;

interface WorkspaceCommandRepository
{
    public function addWorkspace(Workspace $workspace): void;

    public function addMembership(WorkspaceMember $membership): void;

    public function findUserByEmail(string $email): ?User;

    public function findUserById(int $id): ?User;

    public function hasMembership(Workspace $workspace, User $user): bool;

    public function flush(): void;
}
