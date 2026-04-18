<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\SessionResult;

interface ResultUpdatedPublisher
{
    public function publish(SessionResult $result): void;
}

