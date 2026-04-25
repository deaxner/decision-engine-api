<?php

namespace App\Application\DemoData;

final readonly class DemoSeedReport
{
    public function __construct(
        public int $workspaceCount,
        public int $userCount,
        public int $sessionCount,
        public string $defaultPassword,
    ) {
    }
}
