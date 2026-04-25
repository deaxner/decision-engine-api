<?php

namespace App\Infrastructure\Persistence\Decision;

use App\Application\Decision\WorkspaceMembershipRepository;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineWorkspaceMembershipRepository implements WorkspaceMembershipRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findMembership(User $user, Workspace $workspace): ?WorkspaceMember
    {
        $member = $this->entityManager->getRepository(WorkspaceMember::class)->findOneBy([
            'workspace' => $workspace,
            'user' => $user,
        ]);

        return $member instanceof WorkspaceMember ? $member : null;
    }
}
