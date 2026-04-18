<?php

namespace App\Application\Decision\Handler;

use App\Application\Decision\Message\VoteCastEvent;
use App\Application\Decision\ResultCalculator;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\Vote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class VoteCastEventHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ResultCalculator $results,
    ) {
    }

    public function __invoke(VoteCastEvent $event): void
    {
        $session = $this->entityManager->find(DecisionSession::class, $event->sessionId);
        $vote = $this->entityManager->find(Vote::class, $event->voteId);
        if (!$session instanceof DecisionSession || !$vote instanceof Vote) {
            throw new \DomainException('Cannot recompute results for a missing session or vote.');
        }

        $this->results->recompute($session);
    }
}

