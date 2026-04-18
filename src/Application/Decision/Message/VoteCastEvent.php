<?php

namespace App\Application\Decision\Message;

final readonly class VoteCastEvent
{
    public function __construct(
        public int $sessionId,
        public int $voteId,
    ) {
    }
}

