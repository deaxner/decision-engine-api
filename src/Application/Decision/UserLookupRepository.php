<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\User;

interface UserLookupRepository
{
    public function findUserById(int $id): ?User;
}
