<?php

namespace App\UI\Http\Controller;

use App\Domain\Decision\Entity\User;
use App\Infrastructure\Security\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends ApiController
{
    #[Route('/register', methods: ['POST'])]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher, TokenService $tokens): JsonResponse
    {
        try {
            $body = $this->body($request);
            foreach (['email', 'password', 'display_name'] as $field) {
                if (!isset($body[$field]) || !is_string($body[$field]) || trim($body[$field]) === '') {
                    throw new \DomainException(sprintf('Missing required field: %s.', $field));
                }
            }

            if ($entityManager->getRepository(User::class)->findOneBy(['email' => strtolower($body['email'])])) {
                throw new \DomainException('Email is already registered.');
            }

            $user = new User($body['email'], '', $body['display_name']);
            $password = $hasher->hashPassword($user, $body['password']);
            $user = new User($body['email'], $password, $body['display_name']);
            $entityManager->persist($user);
            $entityManager->flush();

            return $this->ok(['user' => $this->userPayload($user), 'token' => $tokens->issue($user)], 201);
        } catch (\Throwable $exception) {
            return $this->fail($exception);
        }
    }

    #[Route('/login', methods: ['POST'])]
    public function login(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher, TokenService $tokens): JsonResponse
    {
        try {
            $body = $this->body($request);
            $user = isset($body['email']) ? $entityManager->getRepository(User::class)->findOneBy(['email' => strtolower((string) $body['email'])]) : null;
            if (!$user instanceof User || !isset($body['password']) || !$hasher->isPasswordValid($user, (string) $body['password'])) {
                throw new \DomainException('Invalid credentials.');
            }

            return $this->ok(['user' => $this->userPayload($user), 'token' => $tokens->issue($user)]);
        } catch (\Throwable $exception) {
            return $this->fail($exception, 401);
        }
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'display_name' => $user->getDisplayName(),
        ];
    }
}

