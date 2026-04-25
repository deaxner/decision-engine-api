<?php

namespace App\Application\Decision\Output;

final readonly class ActivityEventOutput implements \JsonSerializable
{
    /**
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $summary,
        public ?array $actor,
        public string $workspaceId,
        public ?string $sessionId,
        public ?string $sessionTitle,
        public string $createdAt,
        public array $metadata,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'summary' => $this->summary,
            'actor' => $this->actor,
            'workspace_id' => $this->workspaceId,
            'session_id' => $this->sessionId,
            'session_title' => $this->sessionTitle,
            'created_at' => $this->createdAt,
            'metadata' => $this->metadata,
        ];
    }
}
