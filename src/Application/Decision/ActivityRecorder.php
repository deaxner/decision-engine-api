<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\ActivityEvent;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;

final readonly class ActivityRecorder
{
    public function __construct(private ActivityEventStore $events)
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
        $this->events->add($event);

        return $event;
    }
}
