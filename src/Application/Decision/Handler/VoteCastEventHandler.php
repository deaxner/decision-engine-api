<?php

namespace App\Application\Decision\Handler;

use App\Application\Decision\Message\VoteCastEvent;
use App\Application\Decision\DecisionMessageTargetRepository;
use App\Application\Decision\ResultRecomputer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class VoteCastEventHandler
{
    public function __construct(
        private DecisionMessageTargetRepository $targets,
        private ResultRecomputer $results,
    ) {
    }

    public function __invoke(VoteCastEvent $event): void
    {
        $session = $this->targets->findSessionById($event->sessionId);
        $vote = $this->targets->findVoteById($event->voteId);
        if ($session === null || $vote === null) {
            throw new \DomainException('Cannot recompute results for a missing session or vote.');
        }

        $this->results->recompute($session);
    }
}
