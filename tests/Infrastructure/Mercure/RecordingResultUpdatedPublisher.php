<?php

namespace App\Tests\Infrastructure\Mercure;

use App\Application\Decision\ResultUpdatedPublisher;
use App\Domain\Decision\Entity\SessionResult;

final class RecordingResultUpdatedPublisher implements ResultUpdatedPublisher
{
    /** @var array<int, array<string, mixed>> */
    private static array $published = [];

    public function publish(SessionResult $result): void
    {
        self::$published[] = ['type' => 'result_updated'] + $result->toArray();
    }

    public static function reset(): void
    {
        self::$published = [];
    }

    /** @return array<int, array<string, mixed>> */
    public static function published(): array
    {
        return self::$published;
    }
}

