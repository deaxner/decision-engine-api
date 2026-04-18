<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\DecisionSession;

final class VotePayloadValidator
{
    public function validate(string $votingType, array $payload, array $optionIds): void
    {
        if (($payload['version'] ?? null) !== 1 || ($payload['type'] ?? null) !== $votingType || !isset($payload['data']) || !is_array($payload['data'])) {
            throw new \DomainException('Vote payload must include version 1, matching type, and data.');
        }

        match ($votingType) {
            DecisionSession::MAJORITY => $this->validateMajority($payload, $optionIds),
            DecisionSession::RANKED_IRV => $this->validateRankedIrv($payload, $optionIds),
            default => throw new \DomainException('Unsupported voting type.'),
        };
    }

    private function validateMajority(array $payload, array $optionIds): void
    {
        $choice = $payload['data']['choice'] ?? null;
        if (!is_numeric($choice) || !in_array((int) $choice, $optionIds, true)) {
            throw new \DomainException('Majority payload must include a valid choice option id.');
        }
    }

    private function validateRankedIrv(array $payload, array $optionIds): void
    {
        $ranking = $payload['data']['ranking'] ?? null;
        if (!is_array($ranking) || $ranking === []) {
            throw new \DomainException('Ranked IRV payload must include a non-empty ranking.');
        }

        $seen = [];
        foreach ($ranking as $optionId) {
            if (!is_numeric($optionId) || !in_array((int) $optionId, $optionIds, true) || isset($seen[(int) $optionId])) {
                throw new \DomainException('Ranked IRV ranking must contain unique valid option ids.');
            }
            $seen[(int) $optionId] = true;
        }
    }
}

