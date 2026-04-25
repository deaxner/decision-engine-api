<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\Vote;

interface ResultComputationRepository
{
    /**
     * @return list<Vote>
     */
    public function votesForSession(DecisionSession $session): array;

    public function resultForSession(DecisionSession $session): ?SessionResult;

    public function addResult(SessionResult $result): void;

    public function flush(): void;
}
