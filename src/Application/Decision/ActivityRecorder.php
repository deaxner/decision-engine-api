<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\ActivityEvent;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ActivityRecorder
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function record(
        Workspace $workspace,
        string $type,
        string $summary,
        ?User $actor = null,
        ?DecisionSession $session = null,
        array $metadata = [],
    ): ActivityEvent {
        $event = new ActivityEvent($workspace, $type, $summary, $actor, $session, $metadata);
        $this->entityManager->persist($event);

        return $event;
    }
}
