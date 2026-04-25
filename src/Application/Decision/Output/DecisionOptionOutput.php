<?php

namespace App\Application\Decision\Output;

final readonly class DecisionOptionOutput implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $title,
        public int $position,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'position' => $this->position,
        ];
    }
}
