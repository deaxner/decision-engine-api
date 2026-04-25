<?php

namespace App\Application\Decision\Input;

final readonly class CreateWorkspaceInput
{
    public function __construct(
        public string $name,
        public string $slug,
    ) {
    }
}
