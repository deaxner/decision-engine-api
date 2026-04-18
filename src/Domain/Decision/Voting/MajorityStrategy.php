<?php

namespace App\Domain\Decision\Voting;

final class MajorityStrategy implements VotingStrategy
{
    public function compute(array $optionPositions, array $payloads): array
    {
        $counts = array_fill_keys(array_keys($optionPositions), 0);

        foreach ($payloads as $payload) {
            $choice = (int) ($payload['data']['choice'] ?? 0);
            if (array_key_exists($choice, $counts)) {
                ++$counts[$choice];
            }
        }

        $winner = null;
        foreach ($counts as $optionId => $count) {
            if ($winner === null || $count > $counts[$winner] || ($count === $counts[$winner] && $optionPositions[$optionId] < $optionPositions[$winner])) {
                $winner = (int) $optionId;
            }
        }

        return [
            'winner' => $winner,
            'rounds' => [[
                'type' => 'MAJORITY',
                'counts' => $this->stringKeyedCounts($counts),
            ]],
            'total_votes' => count($payloads),
        ];
    }

    private function stringKeyedCounts(array $counts): array
    {
        $result = [];
        foreach ($counts as $optionId => $count) {
            $result[(string) $optionId] = $count;
        }

        return $result;
    }
}

