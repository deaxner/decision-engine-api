<?php

namespace App\Infrastructure\Messaging;

use Predis\Client;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class PredisMessengerTransport implements TransportInterface
{
    private string $processingQueue;

    /** @var array<string, string> */
    private array $processingMessages = [];

    public function __construct(
        private readonly Client $redis,
        private readonly SerializerInterface $serializer,
        private readonly string $queue,
    ) {
        $this->processingQueue = $queue.':processing';
    }

    public function send(Envelope $envelope): Envelope
    {
        $encoded = $this->serializer->encode($envelope);
        $id = bin2hex(random_bytes(16));
        $message = json_encode(['id' => $id] + $encoded, JSON_THROW_ON_ERROR);
        $this->redis->lpush($this->queue, [$message]);

        return $envelope->with(new TransportMessageIdStamp($id));
    }

    public function get(): iterable
    {
        $message = $this->redis->brpoplpush($this->queue, $this->processingQueue, 1);
        if (!is_string($message)) {
            return [];
        }

        $decoded = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
        $id = (string) $decoded['id'];
        $this->processingMessages[$id] = $message;

        return [
            $this->serializer->decode([
                'body' => (string) $decoded['body'],
                'headers' => $decoded['headers'] ?? [],
            ])->with(new TransportMessageIdStamp($id)),
        ];
    }

    public function ack(Envelope $envelope): void
    {
        $this->removeFromProcessing($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->removeFromProcessing($envelope);
    }

    private function removeFromProcessing(Envelope $envelope): void
    {
        $stamp = $envelope->last(TransportMessageIdStamp::class);
        if (!$stamp instanceof TransportMessageIdStamp) {
            return;
        }

        $id = $stamp->getId();
        if (!isset($this->processingMessages[$id])) {
            return;
        }

        $this->redis->lrem($this->processingQueue, 1, $this->processingMessages[$id]);
        unset($this->processingMessages[$id]);
    }
}

