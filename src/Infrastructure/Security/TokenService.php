<?php

namespace App\Infrastructure\Security;

use App\Domain\Decision\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TokenService
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    public function issue(User $user): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode([
            'sub' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'iat' => time(),
        ], JSON_THROW_ON_ERROR));
        $signature = $this->sign($header.'.'.$payload);

        return $header.'.'.$payload.'.'.$signature;
    }

    public function userIdFromAuthorization(?string $authorization): int
    {
        if ($authorization === null || !str_starts_with($authorization, 'Bearer ')) {
            throw new \DomainException('Missing bearer token.');
        }

        $token = substr($authorization, 7);
        $parts = explode('.', $token);
        if (count($parts) !== 3 || !hash_equals($this->sign($parts[0].'.'.$parts[1]), $parts[2])) {
            throw new \DomainException('Invalid bearer token.');
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true, 512, JSON_THROW_ON_ERROR);
        if (!isset($payload['sub']) || !is_numeric($payload['sub'])) {
            throw new \DomainException('Invalid bearer token subject.');
        }

        return (int) $payload['sub'];
    }

    private function sign(string $value): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $value, $this->secret, true));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true) ?: '';
    }
}

