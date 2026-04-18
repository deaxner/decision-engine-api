<?php

namespace App\Domain\Decision\Voting;

interface VotingStrategy
{
    /**
     * @param array<int, int> $optionPositions option id => position
     * @param array<int, array<string, mixed>> $payloads latest active vote payloads
     *
     * @return array{winner: ?int, rounds: array<int, array<string, mixed>>, total_votes: int}
     */
    public function compute(array $optionPositions, array $payloads): array;
}

