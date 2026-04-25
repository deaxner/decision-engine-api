<?php

namespace App\Infrastructure\Persistence\Decision;

use App\Application\Decision\SessionCommandRepository;
use App\Domain\Decision\Entity\DecisionOption;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineSessionCommandRepository implements SessionCommandRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function addSession(DecisionSession $session): void
    {
        $this->entityManager->persist($session);
    }

    public function addOption(DecisionOption $option): void
    {
        $this->entityManager->persist($option);
    }

    public function findUserById(int $id): ?User
    {
        $user = $this->entityManager->find(User::class, $id);

        return $user instanceof User ? $user : null;
    }

    public function hasWorkspaceMembership(Workspace $workspace, User $user): bool
    {
        return $this->entityManager->getRepository(WorkspaceMember::class)->findOneBy([
            'workspace' => $workspace,
            'user' => $user,
        ]) instanceof WorkspaceMember;
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
