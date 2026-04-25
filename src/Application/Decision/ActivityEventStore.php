<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\ActivityEvent;

interface ActivityEventStore
{
    public function add(ActivityEvent $event): void;
}
