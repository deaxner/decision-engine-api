<?php

namespace App\Infrastructure\Persistence\Decision;

use App\Application\Decision\DecisionMessageTargetRepository;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\Vote;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineDecisionMessageTargetRepository implements DecisionMessageTargetRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findSessionById(int $id): ?DecisionSession
    {
        $session = $this->entityManager->find(DecisionSession::class, $id);

        return $session instanceof DecisionSession ? $session : null;
    }

    public function findVoteById(int $id): ?Vote
    {
        $vote = $this->entityManager->find(Vote::class, $id);

        return $vote instanceof Vote ? $vote : null;
    }
}
