<?php

namespace App\Infrastructure\Messaging;

use Predis\Client;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class PredisMessengerTransportFactory implements TransportFactoryInterface
{
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $parts = parse_url($dsn);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('Invalid Redis Messenger DSN.');
        }

        $queue = trim($parts['path'] ?? '', '/') ?: ($options['queue_name'] ?? 'messages');
        $redisDsn = sprintf(
            '%s://%s%s',
            $parts['scheme'],
            $parts['host'],
            isset($parts['port']) ? ':'.$parts['port'] : '',
        );

        return new PredisMessengerTransport(new Client($redisDsn), $serializer, $queue);
    }

    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'redis://') || str_starts_with($dsn, 'rediss://');
    }
}

