<?php

namespace App\Infrastructure\Persistence\Decision;

use App\Application\Decision\SessionReadRepository;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineSessionReadRepository implements SessionReadRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function sessionsForWorkspace(Workspace $workspace): array
    {
        return $this->entityManager->getRepository(DecisionSession::class)->findBy(
            ['workspace' => $workspace],
            ['createdAt' => 'DESC'],
        );
    }

    public function resultForSession(DecisionSession $session): ?SessionResult
    {
        $result = $this->entityManager->getRepository(SessionResult::class)->find($session);

        return $result instanceof SessionResult ? $result : null;
    }
}
