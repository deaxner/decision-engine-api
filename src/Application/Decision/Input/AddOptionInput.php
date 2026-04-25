<?php

namespace App\Application\Decision\Input;

final readonly class AddOptionInput
{
    public function __construct(
        public string $title,
        public ?int $position,
    ) {
    }
}
