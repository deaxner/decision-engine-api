<?php

namespace App\Infrastructure\Persistence\Decision;

use App\Application\Decision\WorkspaceCommandRepository;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineWorkspaceCommandRepository implements WorkspaceCommandRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function addWorkspace(Workspace $workspace): void
    {
        $this->entityManager->persist($workspace);
    }

    public function addMembership(WorkspaceMember $membership): void
    {
        $this->entityManager->persist($membership);
    }

    public function findUserByEmail(string $email): ?User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        return $user instanceof User ? $user : null;
    }

    public function findUserById(int $id): ?User
    {
        $user = $this->entityManager->find(User::class, $id);

        return $user instanceof User ? $user : null;
    }

    public function hasMembership(Workspace $workspace, User $user): bool
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
