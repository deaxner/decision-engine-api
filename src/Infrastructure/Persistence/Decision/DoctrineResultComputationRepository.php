<?php

namespace App\Infrastructure\Persistence\Decision;

use App\Application\Decision\ResultComputationRepository;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\Vote;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineResultComputationRepository implements ResultComputationRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function votesForSession(DecisionSession $session): array
    {
        return $this->entityManager->getRepository(Vote::class)->findBy(
            ['session' => $session],
            ['createdAt' => 'DESC', 'id' => 'DESC'],
        );
    }

    public function resultForSession(DecisionSession $session): ?SessionResult
    {
        $result = $this->entityManager->getRepository(SessionResult::class)->find($session);

        return $result instanceof SessionResult ? $result : null;
    }

    public function addResult(SessionResult $result): void
    {
        $this->entityManager->persist($result);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
