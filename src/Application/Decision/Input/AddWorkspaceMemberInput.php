<?php

namespace App\Application\Decision\Input;

final readonly class AddWorkspaceMemberInput
{
    public function __construct(
        public ?string $email,
        public ?int $userId,
    ) {
    }
}
