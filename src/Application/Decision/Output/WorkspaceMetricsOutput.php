<?php

namespace App\Application\Decision\Output;

final readonly class WorkspaceMetricsOutput implements \JsonSerializable
{
    public function __construct(
        public ?float $decisionSpeedDays,
        public int $engagementRate,
        public int $activeSessionCount,
        public int $draftSessionCount,
        public int $closedSessionCount,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'decision_speed_days' => $this->decisionSpeedDays,
            'engagement_rate' => $this->engagementRate,
            'active_session_count' => $this->activeSessionCount,
            'draft_session_count' => $this->draftSessionCount,
            'closed_session_count' => $this->closedSessionCount,
        ];
    }
}
