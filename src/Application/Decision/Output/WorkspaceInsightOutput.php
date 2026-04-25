<?php

namespace App\Application\Decision\Output;

final readonly class WorkspaceInsightOutput implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $severity,
        public string $title,
        public string $body,
        public ?string $sessionId,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'severity' => $this->severity,
            'title' => $this->title,
            'body' => $this->body,
            'session_id' => $this->sessionId,
        ];
    }
}
