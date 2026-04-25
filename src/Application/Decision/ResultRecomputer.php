<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;

interface ResultRecomputer
{
    public function recompute(DecisionSession $session): SessionResult;
}
