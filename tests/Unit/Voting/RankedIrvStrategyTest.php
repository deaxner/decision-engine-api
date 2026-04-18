<?php

namespace App\Tests\Unit\Voting;

use App\Domain\Decision\Voting\RankedIrvStrategy;
use PHPUnit\Framework\TestCase;

final class RankedIrvStrategyTest extends TestCase
{
    public function testFirstRoundMajority(): void
    {
        $result = (new RankedIrvStrategy())->compute([1 => 1, 2 => 2, 3 => 3], [
            ['data' => ['ranking' => [1, 2, 3]]],
            ['data' => ['ranking' => [1, 3, 2]]],
            ['data' => ['ranking' => [2, 1, 3]]],
        ]);

        self::assertSame(1, $result['winner']);
        self::assertCount(1, $result['rounds']);
    }

    public function testEliminationAndRedistribution(): void
    {
        $result = (new RankedIrvStrategy())->compute([1 => 1, 2 => 2, 3 => 3], [
            ['data' => ['ranking' => [1, 2, 3]]],
            ['data' => ['ranking' => [2, 1, 3]]],
            ['data' => ['ranking' => [3, 2, 1]]],
        ]);

        self::assertSame(2, $result['winner']);
        self::assertSame('3', $result['rounds'][0]['eliminated_option_id']);
    }

    public function testExhaustedBallotsStillProduceDeterministicWinner(): void
    {
        $result = (new RankedIrvStrategy())->compute([1 => 1, 2 => 2], [
            ['data' => ['ranking' => [1]]],
            ['data' => ['ranking' => [2]]],
        ]);

        self::assertSame(1, $result['winner']);
    }

    public function testEliminationTieUsesHighestOptionPosition(): void
    {
        $result = (new RankedIrvStrategy())->compute([1 => 1, 2 => 2, 3 => 3], [
            ['data' => ['ranking' => [1, 2, 3]]],
            ['data' => ['ranking' => [2, 1, 3]]],
            ['data' => ['ranking' => [3, 1, 2]]],
        ]);

        self::assertSame('3', $result['rounds'][0]['eliminated_option_id']);
    }
}

