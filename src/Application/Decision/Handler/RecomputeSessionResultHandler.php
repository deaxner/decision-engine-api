<?php

namespace App\Application\Decision\Handler;

use App\Application\Decision\Message\RecomputeSessionResult;
use App\Application\Decision\DecisionMessageTargetRepository;
use App\Application\Decision\ResultRecomputer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecomputeSessionResultHandler
{
    public function __construct(
        private DecisionMessageTargetRepository $targets,
        private ResultRecomputer $results,
    ) {
    }

    public function __invoke(RecomputeSessionResult $message): void
    {
        $session = $this->targets->findSessionById($message->sessionId);
        if ($session === null) {
            throw new \DomainException('Cannot recompute results for a missing session.');
        }

        $this->results->recompute($session);
    }
}
