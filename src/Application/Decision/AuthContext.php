<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\User;
use App\Infrastructure\Security\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class AuthContext
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenService $tokens,
    ) {
    }

    public function user(Request $request): User
    {
        $userId = $this->tokens->userIdFromAuthorization($request->headers->get('Authorization'));
        $user = $this->entityManager->find(User::class, $userId);
        if (!$user instanceof User) {
            throw new \DomainException('Authenticated user was not found.');
        }

        return $user;
    }
}

