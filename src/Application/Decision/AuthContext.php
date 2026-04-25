<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\User;
use App\Infrastructure\Security\TokenService;
use Symfony\Component\HttpFoundation\Request;

final class AuthContext
{
    public function __construct(
        private readonly UserLookupRepository $users,
        private readonly TokenService $tokens,
    ) {
    }

    public function user(Request $request): User
    {
        $userId = $this->tokens->userIdFromAuthorization($request->headers->get('Authorization'));
        $user = $this->users->findUserById($userId);
        if (!$user instanceof User) {
            throw new \DomainException('Authenticated user was not found.');
        }

        return $user;
    }
}
