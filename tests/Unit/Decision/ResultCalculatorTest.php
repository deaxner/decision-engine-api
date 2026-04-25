<?php

namespace App\Tests\Unit\Decision;

use App\Application\Decision\ActivityRecorder;
use App\Application\Decision\ActivityEventStore;
use App\Application\Decision\ResultCalculator;
use App\Application\Decision\ResultComputationRepository;
use App\Application\Decision\ResultUpdatedPublisher;
use App\Domain\Decision\Entity\DecisionOption;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Vote;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Voting\MajorityStrategy;
use App\Domain\Decision\Voting\RankedIrvStrategy;
use PHPUnit\Framework\TestCase;

final class ResultCalculatorTest extends TestCase
{
    public function testRecomputePersistsUpdatedResultThroughRepositoryPort(): void
    {
        $owner = new User('owner@example.test', 'hash', 'Owner');
        $workspace = new Workspace('Product', 'product', $owner);
        $session = new DecisionSession($workspace, $owner, 'Choose vendor', null, DecisionSession::MAJORITY, null, null);
        $optionA = new DecisionOption($session, 'Option A', 1);
        $optionB = new DecisionOption($session, 'Option B', 2);
        $this->setEntityId($optionA, 1);
        $this->setEntityId($optionB, 2);
        $session->addOption($optionA);
        $session->addOption($optionB);

        $vote = new Vote($session, $owner, [
            'version' => 1,
            'type' => DecisionSession::MAJORITY,
            'data' => ['choice' => 1],
        ]);
        $this->setEntityId($owner, 10);

        $repository = $this->createMock(ResultComputationRepository::class);
        $repository->expects($this->once())->method('votesForSession')->with($session)->willReturn([$vote]);
        $repository->expects($this->once())->method('resultForSession')->with($session)->willReturn(null);
        $repository->expects($this->once())->method('addResult')->with($this->isInstanceOf(SessionResult::class));
        $repository->expects($this->once())->method('flush');

        $publisher = $this->createMock(ResultUpdatedPublisher::class);
        $publisher->expects($this->once())->method('publish')->with($this->isInstanceOf(SessionResult::class));

        $calculator = new ResultCalculator(
            $repository,
            new MajorityStrategy(),
            new RankedIrvStrategy(),
            $publisher,
            new ActivityRecorder($this->createStub(ActivityEventStore::class)),
        );

        $result = $calculator->recompute($session);

        self::assertSame(1, $result->getVersion());
        self::assertSame('1', $result->toArray()['winning_option_id']);
    }

    private function setEntityId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
