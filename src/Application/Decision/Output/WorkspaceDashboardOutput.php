<?php

namespace App\Application\Decision\Output;

final readonly class WorkspaceDashboardOutput implements \JsonSerializable
{
    /**
     * @param list<ActivityEventOutput> $activity
     * @param list<WorkspaceInsightOutput> $insights
     */
    public function __construct(
        public WorkspaceOutput $workspace,
        public WorkspaceMetricsOutput $metrics,
        public array $activity,
        public array $insights,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'workspace' => $this->workspace,
            'metrics' => $this->metrics,
            'activity' => $this->activity,
            'insights' => $this->insights,
        ];
    }
}
