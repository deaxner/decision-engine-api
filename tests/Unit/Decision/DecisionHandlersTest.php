<?php

namespace App\Tests\Unit\Decision;

use App\Application\Decision\DecisionMessageTargetRepository;
use App\Application\Decision\Handler\RecomputeSessionResultHandler;
use App\Application\Decision\Handler\VoteCastEventHandler;
use App\Application\Decision\Message\RecomputeSessionResult;
use App\Application\Decision\Message\VoteCastEvent;
use App\Application\Decision\ResultRecomputer;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Vote;
use App\Domain\Decision\Entity\Workspace;
use PHPUnit\Framework\TestCase;

final class DecisionHandlersTest extends TestCase
{
    public function testVoteCastHandlerResolvesTargetsThroughRepositoryPort(): void
    {
        $owner = new User('owner@example.test', 'hash', 'Owner');
        $workspace = new Workspace('Product', 'product', $owner);
        $session = new DecisionSession($workspace, $owner, 'Choose vendor', null, DecisionSession::MAJORITY, null, null);
        $vote = new Vote($session, $owner, ['version' => 1, 'type' => DecisionSession::MAJORITY, 'data' => ['choice' => 1]]);

        $targets = $this->createMock(DecisionMessageTargetRepository::class);
        $targets->expects($this->once())->method('findSessionById')->with(3)->willReturn($session);
        $targets->expects($this->once())->method('findVoteById')->with(4)->willReturn($vote);

        $results = $this->createMock(ResultRecomputer::class);
        $results->expects($this->once())->method('recompute')->with($session);

        $handler = new VoteCastEventHandler($targets, $results);
        $handler(new VoteCastEvent(3, 4));
    }

    public function testRecomputeHandlerResolvesSessionThroughRepositoryPort(): void
    {
        $owner = new User('owner@example.test', 'hash', 'Owner');
        $workspace = new Workspace('Product', 'product', $owner);
        $session = new DecisionSession($workspace, $owner, 'Choose vendor', null, DecisionSession::MAJORITY, null, null);

        $targets = $this->createMock(DecisionMessageTargetRepository::class);
        $targets->expects($this->once())->method('findSessionById')->with(9)->willReturn($session);

        $results = $this->createMock(ResultRecomputer::class);
        $results->expects($this->once())->method('recompute')->with($session);

        $handler = new RecomputeSessionResultHandler($targets, $results);
        $handler(new RecomputeSessionResult(9, 'session_closed'));
    }
}
