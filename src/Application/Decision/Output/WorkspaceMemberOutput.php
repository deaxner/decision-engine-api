<?php

namespace App\Application\Decision\Output;

final readonly class WorkspaceMemberOutput implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public string $role,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'display_name' => $this->displayName,
            'role' => $this->role,
        ];
    }
}
