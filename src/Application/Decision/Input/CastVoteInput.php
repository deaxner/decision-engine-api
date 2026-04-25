<?php

namespace App\Application\Decision\Input;

final readonly class CastVoteInput
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public array $payload,
    ) {
    }
}
