<?php

namespace App\Infrastructure\Mercure;

use App\Application\Decision\ResultUpdatedPublisher;
use App\Domain\Decision\Entity\SessionResult;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final readonly class MercureResultUpdatedPublisher implements ResultUpdatedPublisher
{
    public function __construct(private HubInterface $hub)
    {
    }

    public function publish(SessionResult $result): void
    {
        $payload = ['type' => 'result_updated'] + $result->toArray();
        $sessionId = $payload['session_id'];

        $this->hub->publish(new Update(
            "/sessions/{$sessionId}/results",
            json_encode($payload, JSON_THROW_ON_ERROR),
            type: 'result_updated',
        ));
    }
}

