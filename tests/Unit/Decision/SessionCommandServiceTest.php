<?php

namespace App\Tests\Unit\Decision;

use App\Application\Decision\ActivityRecorder;
use App\Application\Decision\ActivityEventStore;
use App\Application\Decision\Input\AddOptionInput;
use App\Application\Decision\Input\CreateSessionInput;
use App\Application\Decision\Input\UpdateSessionStatusInput;
use App\Application\Decision\Message\RecomputeSessionResult;
use App\Application\Decision\SessionCommandRepository;
use App\Application\Decision\SessionCommandService;
use App\Domain\Decision\Entity\DecisionOption;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class SessionCommandServiceTest extends TestCase
{
    public function testCreateSessionResolvesAssigneesThroughRepositoryPort(): void
    {
        $owner = new User('owner@example.test', 'hash', 'Owner');
        $assignee = new User('member@example.test', 'hash', 'Member');
        $workspace = new Workspace('Product', 'product', $owner);
        $repository = $this->createMock(SessionCommandRepository::class);

        $repository->expects($this->once())->method('findUserById')->with(7)->willReturn($assignee);
        $repository->expects($this->once())->method('hasWorkspaceMembership')->with($workspace, $assignee)->willReturn(true);
        $repository->expects($this->once())->method('addSession')->with($this->isInstanceOf(DecisionSession::class));
        $repository->expects($this->once())->method('flush');

        $events = $this->createStub(ActivityEventStore::class);
        $bus = $this->createStub(MessageBusInterface::class);
        $service = new SessionCommandService($repository, new ActivityRecorder($events), $bus);

        $session = $service->createSession($owner, $workspace, new CreateSessionInput(
            'Choose vendor',
            null,
            DecisionSession::MAJORITY,
            'Procurement',
            null,
            ['7'],
        ));

        self::assertSame('Choose vendor', $session->getTitle());
        self::assertCount(1, $session->getAssignees());
    }

    public function testAddOptionPersistsThroughRepositoryPort(): void
    {
        $owner = new User('owner@example.test', 'hash', 'Owner');
        $workspace = new Workspace('Product', 'product', $owner);
        $session = new DecisionSession($workspace, $owner, 'Choose vendor', null, DecisionSession::MAJORITY, null, null);
        $repository = $this->createMock(SessionCommandRepository::class);

        $repository->expects($this->once())->method('addOption')->with($this->callback(
            fn (DecisionOption $option) => $option->getTitle() === 'Option A' && $option->getPosition() === 1
        ));
        $repository->expects($this->once())->method('flush');

        $service = new SessionCommandService(
            $repository,
            new ActivityRecorder($this->createStub(ActivityEventStore::class)),
            $this->createStub(MessageBusInterface::class),
        );

        $option = $service->addOption($owner, $session, new AddOptionInput('Option A', null));

        self::assertSame('Option A', $option->getTitle());
        self::assertSame(1, $option->getPosition());
    }

    public function testClosingSessionDispatchesRecomputeMessage(): void
    {
        $owner = new User('owner@example.test', 'hash', 'Owner');
        $workspace = new Workspace('Product', 'product', $owner);
        $session = new DecisionSession($workspace, $owner, 'Choose vendor', null, DecisionSession::MAJORITY, null, null);
        $session->addOption(new DecisionOption($session, 'Option A', 1));
        $session->addOption(new DecisionOption($session, 'Option B', 2));
        $session->open();
        $repository = $this->createMock(SessionCommandRepository::class);
        $repository->expects($this->once())->method('flush');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn (RecomputeSessionResult $message) => $message->reason === 'session_closed'))
            ->willReturn(new Envelope(new \stdClass()));

        $service = new SessionCommandService(
            $repository,
            new ActivityRecorder($this->createStub(ActivityEventStore::class)),
            $bus,
        );

        $service->updateStatus($owner, $session, new UpdateSessionStatusInput(DecisionSession::CLOSED));

        self::assertSame(DecisionSession::CLOSED, $session->getStatus());
    }
}
