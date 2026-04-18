<?php

namespace App\Application\Decision\Handler;

use App\Application\Decision\Message\RecomputeSessionResult;
use App\Application\Decision\ResultCalculator;
use App\Domain\Decision\Entity\DecisionSession;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecomputeSessionResultHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ResultCalculator $results,
    ) {
    }

    public function __invoke(RecomputeSessionResult $message): void
    {
        $session = $this->entityManager->find(DecisionSession::class, $message->sessionId);
        if (!$session instanceof DecisionSession) {
            throw new \DomainException('Cannot recompute results for a missing session.');
        }

        $this->results->recompute($session);
    }
}

