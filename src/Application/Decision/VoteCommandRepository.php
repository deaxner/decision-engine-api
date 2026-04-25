<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Vote;

interface VoteCommandRepository
{
    public function findExistingVote(DecisionSession $session, User $user): ?Vote;

    public function addVote(Vote $vote): void;

    public function flush(): void;
}
