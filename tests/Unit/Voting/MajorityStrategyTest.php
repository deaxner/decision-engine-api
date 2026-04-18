<?php

namespace App\Tests\Unit\Voting;

use App\Domain\Decision\Voting\MajorityStrategy;
use PHPUnit\Framework\TestCase;

final class MajorityStrategyTest extends TestCase
{
    public function testClearWinner(): void
    {
        $result = (new MajorityStrategy())->compute([10 => 1, 20 => 2], [
            ['data' => ['choice' => 10]],
            ['data' => ['choice' => 10]],
            ['data' => ['choice' => 20]],
        ]);

        self::assertSame(10, $result['winner']);
        self::assertSame(3, $result['total_votes']);
    }

    public function testTieResolvedByLowestOptionPosition(): void
    {
        $result = (new MajorityStrategy())->compute([10 => 2, 20 => 1], [
            ['data' => ['choice' => 10]],
            ['data' => ['choice' => 20]],
        ]);

        self::assertSame(20, $result['winner']);
    }
}

