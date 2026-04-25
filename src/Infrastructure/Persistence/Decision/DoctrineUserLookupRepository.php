<?php

namespace App\Infrastructure\Persistence\Decision;

use App\Application\Decision\UserLookupRepository;
use App\Domain\Decision\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineUserLookupRepository implements UserLookupRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findUserById(int $id): ?User
    {
        $user = $this->entityManager->find(User::class, $id);

        return $user instanceof User ? $user : null;
    }
}
