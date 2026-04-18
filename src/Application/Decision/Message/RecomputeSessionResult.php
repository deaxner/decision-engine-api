<?php

namespace App\Application\Decision\Message;

final readonly class RecomputeSessionResult
{
    public function __construct(
        public int $sessionId,
        public string $reason,
    ) {
    }
}

