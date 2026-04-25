<?php

namespace App\Tests\Unit\Decision;

use App\Application\Decision\SessionReadModelQuery;
use App\Application\Decision\SessionReadRepository;
use App\Application\Decision\SessionResultViewFactory;
use App\Application\Decision\SessionViewFactory;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use PHPUnit\Framework\TestCase;

final class SessionReadModelQueryTest extends TestCase
{
    public function testListForWorkspaceUsesRepositoryPortAndViewFactory(): void
    {
        $owner = new User('owner@example.test', 'hash', 'Owner');
        $workspace = new Workspace('Product', 'product', $owner);
        $session = new DecisionSession($workspace, $owner, 'Choose vendor', null, DecisionSession::MAJORITY, null, null);
        $repository = $this->createMock(SessionReadRepository::class);
        $repository->expects($this->once())
            ->method('sessionsForWorkspace')
            ->with($workspace)
            ->willReturn([$session]);

        $factory = new SessionViewFactory();
        $query = new SessionReadModelQuery($repository, $factory, new SessionResultViewFactory());

        self::assertEquals([$factory->payload($session)], $query->listForWorkspace($workspace));
    }

    public function testResultReturnsNullWhenRepositoryHasNoSnapshot(): void
    {
        $owner = new User('owner@example.test', 'hash', 'Owner');
        $workspace = new Workspace('Product', 'product', $owner);
        $session = new DecisionSession($workspace, $owner, 'Choose vendor', null, DecisionSession::MAJORITY, null, null);

        $repository = $this->createMock(SessionReadRepository::class);
        $repository->expects($this->once())
            ->method('resultForSession')
            ->with($session)
            ->willReturn(null);

        $query = new SessionReadModelQuery(
            $repository,
            new SessionViewFactory(),
            new SessionResultViewFactory(),
        );

        self::assertNull($query->result($session));
    }

    public function testResultMapsRepositorySnapshotThroughResultFactory(): void
    {
        $owner = new User('owner@example.test', 'hash', 'Owner');
        $workspace = new Workspace('Product', 'product', $owner);
        $session = new DecisionSession($workspace, $owner, 'Choose vendor', null, DecisionSession::MAJORITY, null, null);
        $result = new SessionResult($session, ['winner' => 'A'], 2);
        $resultFactory = new SessionResultViewFactory();

        $repository = $this->createMock(SessionReadRepository::class);
        $repository->expects($this->once())
            ->method('resultForSession')
            ->with($session)
            ->willReturn($result);

        $query = new SessionReadModelQuery(
            $repository,
            new SessionViewFactory(),
            $resultFactory,
        );

        self::assertEquals($resultFactory->payload($result), $query->result($session));
    }
}
