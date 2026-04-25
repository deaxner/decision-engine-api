<?php

namespace App\Application\Decision\Output;

final readonly class SessionOutput implements \JsonSerializable
{
    /**
     * @param list<SessionAssigneeOutput> $assignees
     * @param list<DecisionOptionOutput> $options
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public ?string $category,
        public string $status,
        public string $votingType,
        public ?string $dueAt,
        public ?string $startsAt,
        public ?string $endsAt,
        public array $assignees,
        public array $options,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'status' => $this->status,
            'voting_type' => $this->votingType,
            'due_at' => $this->dueAt,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'assignees' => $this->assignees,
            'options' => $this->options,
        ];
    }
}
