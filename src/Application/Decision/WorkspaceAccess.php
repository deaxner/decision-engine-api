<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;
use Doctrine\ORM\EntityManagerInterface;

final class WorkspaceAccess
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function requireMember(User $user, Workspace $workspace): WorkspaceMember
    {
        $member = $this->entityManager->getRepository(WorkspaceMember::class)->findOneBy([
            'workspace' => $workspace,
            'user' => $user,
        ]);

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

