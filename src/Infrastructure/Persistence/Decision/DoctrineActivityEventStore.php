<?php

namespace App\Infrastructure\Persistence\Decision;

use App\Application\Decision\ActivityEventStore;
use App\Domain\Decision\Entity\ActivityEvent;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineActivityEventStore implements ActivityEventStore
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function add(ActivityEvent $event): void
    {
        $this->entityManager->persist($event);
    }
}
