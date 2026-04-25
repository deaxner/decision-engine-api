<?php

namespace App\Tests\Unit\Decision;

use App\Application\Decision\AuthContext;
use App\Application\Decision\UserLookupRepository;
use App\Domain\Decision\Entity\User;
use App\Infrastructure\Security\TokenService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class AuthContextTest extends TestCase
{
    public function testUserIsResolvedThroughRepositoryPort(): void
    {
        $user = new User('owner@example.test', 'hash', 'Owner');
        $users = $this->createMock(UserLookupRepository::class);
        $users->expects($this->once())->method('findUserById')->with(15)->willReturn($user);

        $tokens = new TokenService('test-secret');
        $this->setEntityId($user, 15);
        $token = $tokens->issue($user);

        $context = new AuthContext($users, $tokens);

        self::assertSame($user, $context->user(new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token])));
    }

    private function setEntityId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
