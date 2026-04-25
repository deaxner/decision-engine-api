<?php

namespace App\Tests\Unit\Decision;

use App\Application\Decision\ActivityRecorder;
use App\Application\Decision\ActivityEventStore;
use App\Application\Decision\Input\CastVoteInput;
use App\Application\Decision\Message\VoteCastEvent;
use App\Application\Decision\VoteCommandRepository;
use App\Application\Decision\VoteCommandService;
use App\Application\Decision\VotePayloadValidator;
use App\Domain\Decision\Entity\DecisionOption;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Vote;
use App\Domain\Decision\Entity\Workspace;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class VoteCommandServiceTest extends TestCase
{
    public function testCastVotePersistsVoteThroughRepositoryPort(): void
    {
        $user = new User('member@example.test', 'hash', 'Member');
        $workspace = new Workspace('Product', 'product', $user);
        $session = new DecisionSession($workspace, $user, 'Choose vendor', null, DecisionSession::MAJORITY, null, null);
        $optionA = new DecisionOption($session, 'Option A', 1);
        $optionB = new DecisionOption($session, 'Option B', 2);
        $this->setEntityId($optionA, 1);
        $this->setEntityId($optionB, 2);
        $session->addOption($optionA);
        $session->addOption($optionB);
        $session->open();

        $repository = $this->createMock(VoteCommandRepository::class);
        $repository->expects($this->once())->method('findExistingVote')->with($session, $user)->willReturn(null);
        $repository->expects($this->once())->method('addVote')->with($this->isInstanceOf(Vote::class));
        $repository->expects($this->once())->method('flush');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(VoteCastEvent::class))
            ->willReturn(new Envelope(new \stdClass()));

        $service = new VoteCommandService(
            $repository,
            new VotePayloadValidator(),
            new ActivityRecorder($this->createStub(ActivityEventStore::class)),
            $bus,
        );

        $vote = $service->castVote($user, $session, new CastVoteInput([
            'version' => 1,
            'type' => DecisionSession::MAJORITY,
            'data' => ['choice' => 1],
        ]));

        self::assertSame($user, $vote->getUser());
        self::assertSame(['version' => 1, 'type' => DecisionSession::MAJORITY, 'data' => ['choice' => 1]], $vote->getPayload());
    }

    private function setEntityId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
