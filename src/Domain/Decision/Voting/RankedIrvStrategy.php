<?php

namespace App\Domain\Decision\Voting;

final class RankedIrvStrategy implements VotingStrategy
{
    public function compute(array $optionPositions, array $payloads): array
    {
        $active = array_fill_keys(array_keys($optionPositions), true);
        $rounds = [];
        $winner = null;

        while ($active !== []) {
            $counts = array_fill_keys(array_keys($active), 0);
            $activeBallots = 0;

            foreach ($payloads as $payload) {
                $choice = $this->firstActiveChoice($payload['data']['ranking'] ?? [], $active);
                if ($choice !== null) {
                    ++$counts[$choice];
                    ++$activeBallots;
                }
            }

            $roundWinner = $this->majorityWinner($counts, $activeBallots, $optionPositions);
            $eliminated = null;
            if ($roundWinner === null && count($active) > 1) {
                $eliminated = $this->eliminateOption($counts, $optionPositions);
                unset($active[$eliminated]);
            } else {
                $winner = $roundWinner ?? $this->fallbackWinner($counts, $optionPositions);
            }

            $rounds[] = [
                'type' => 'RANKED_IRV',
                'counts' => $this->stringKeyedCounts($counts),
                'active_ballots' => $activeBallots,
                'eliminated_option_id' => $eliminated ? (string) $eliminated : null,
                'winner_option_id' => $winner ? (string) $winner : null,
            ];

            if ($winner !== null || $active === []) {
                break;
            }
        }

        return [
            'winner' => $winner,
            'rounds' => $rounds,
            'total_votes' => count($payloads),
        ];
    }

    private function firstActiveChoice(array $ranking, array $active): ?int
    {
        foreach ($ranking as $optionId) {
            $optionId = (int) $optionId;
            if (isset($active[$optionId])) {
                return $optionId;
            }
        }

        return null;
    }

    private function majorityWinner(array $counts, int $activeBallots, array $optionPositions): ?int
    {
        foreach ($counts as $optionId => $count) {
            if ($activeBallots > 0 && $count > $activeBallots / 2) {
                return (int) $optionId;
            }
        }

        return count($counts) === 1 ? (int) array_key_first($counts) : null;
    }

    private function eliminateOption(array $counts, array $optionPositions): int
    {
        $eliminate = null;
        foreach ($counts as $optionId => $count) {
            if ($eliminate === null || $count < $counts[$eliminate] || ($count === $counts[$eliminate] && $optionPositions[$optionId] > $optionPositions[$eliminate])) {
                $eliminate = (int) $optionId;
            }
        }

        return $eliminate;
    }

    private function fallbackWinner(array $counts, array $optionPositions): ?int
    {
        $winner = null;
        foreach ($counts as $optionId => $count) {
            if ($winner === null || $count > $counts[$winner] || ($count === $counts[$winner] && $optionPositions[$optionId] < $optionPositions[$winner])) {
                $winner = (int) $optionId;
            }
        }

        return $winner;
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

