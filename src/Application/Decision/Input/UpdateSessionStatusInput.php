<?php

namespace App\Application\Decision\Input;

final readonly class UpdateSessionStatusInput
{
    public function __construct(
        public string $status,
    ) {
    }
}
