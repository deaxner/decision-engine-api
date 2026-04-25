<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;

interface WorkspaceMembershipRepository
{
    public function findMembership(User $user, Workspace $workspace): ?WorkspaceMember;
}
