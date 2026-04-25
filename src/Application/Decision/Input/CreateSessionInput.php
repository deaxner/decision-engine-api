<?php

namespace App\Application\Decision\Input;

final readonly class CreateSessionInput
{
    /**
     * @param list<string> $assigneeIds
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public string $votingType,
        public ?string $category,
        public ?\DateTimeImmutable $dueAt,
        public array $assigneeIds,
    ) {
    }
}
