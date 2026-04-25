<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\Vote;

interface DecisionMessageTargetRepository
{
    public function findSessionById(int $id): ?DecisionSession;

    public function findVoteById(int $id): ?Vote;
}
