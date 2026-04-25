<?php

namespace App\Application\Decision\Output;

final readonly class WorkspaceOutput implements \JsonSerializable
{
    /**
     * @param array{total:int,draft:int,open:int,closed:int} $sessionCounts
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public int $memberCount,
        public int $participationRate,
        public array $sessionCounts,
        public ?string $role = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        $payload = [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'member_count' => $this->memberCount,
            'participation_rate' => $this->participationRate,
            'session_counts' => $this->sessionCounts,
        ];

        if ($this->role !== null) {
            $payload['role'] = $this->role;
        }

        return $payload;
    }
}
