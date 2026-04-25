<?php

namespace App\Application\Decision\Output;

final readonly class SessionAssigneeOutput implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $displayName,
        public string $email,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->displayName,
            'email' => $this->email,
        ];
    }
}
