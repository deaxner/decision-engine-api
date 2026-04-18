<?php

namespace App\Infrastructure\Mercure;

use App\Application\Decision\ResultUpdatedPublisher;
use App\Domain\Decision\Entity\SessionResult;

final class NoOpResultUpdatedPublisher implements ResultUpdatedPublisher
{
    public function publish(SessionResult $result): void
    {
    }
}

