<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;

final class WorkspaceAccess
{
    public function __construct(private readonly WorkspaceMembershipRepository $memberships)
    {
    }

    public function requireMember(User $user, Workspace $workspace): WorkspaceMember
    {
        $member = $this->memberships->findMembership($user, $workspace);

        if (!$member instanceof WorkspaceMember) {
            throw new \DomainException('User is not a workspace member.');
        }

        return $member;
    }

    public function requireOwner(User $user, Workspace $workspace): void
    {
        $member = $this->requireMember($user, $workspace);
        if ($member->getRole() !== WorkspaceMember::OWNER) {
            throw new \DomainException('Only workspace owners can perform this action.');
        }
    }
}
