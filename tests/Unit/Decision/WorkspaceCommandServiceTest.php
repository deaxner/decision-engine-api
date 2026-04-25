<?php

namespace App\Tests\Unit\Decision;

use App\Application\Decision\ActivityRecorder;
use App\Application\Decision\ActivityEventStore;
use App\Application\Decision\Input\AddWorkspaceMemberInput;
use App\Application\Decision\Input\CreateWorkspaceInput;
use App\Application\Decision\WorkspaceCommandRepository;
use App\Application\Decision\WorkspaceCommandService;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;
use PHPUnit\Framework\TestCase;

final class WorkspaceCommandServiceTest extends TestCase
{
    public function testCreateWorkspacePersistsWorkspaceAndOwnerMembershipThroughRepository(): void
    {
        $owner = new User('owner@example.test', 'hash', 'Owner');
        $repository = $this->createMock(WorkspaceCommandRepository::class);
        $events = $this->createMock(ActivityEventStore::class);

        $repository->expects($this->once())->method('addWorkspace')->with($this->isInstanceOf(Workspace::class));
        $repository->expects($this->once())->method('addMembership')->with($this->callback(
            fn (WorkspaceMember $membership) => $membership->getUser() === $owner && $membership->getRole() === WorkspaceMember::OWNER
        ));
        $repository->expects($this->once())->method('flush');
        $events->expects($this->once())->method('add');

        $service = new WorkspaceCommandService($repository, new ActivityRecorder($events));

        $workspace = $service->createWorkspace($owner, new CreateWorkspaceInput('Product', 'product'));

        self::assertSame('Product', $workspace->getName());
        self::assertSame('product', $workspace->getSlug());
    }

    public function testAddMemberRejectsExistingMembership(): void
    {
        $owner = new User('owner@example.test', 'hash', 'Owner');
        $member = new User('member@example.test', 'hash', 'Member');
        $workspace = new Workspace('Product', 'product', $owner);
        $repository = $this->createMock(WorkspaceCommandRepository::class);

        $repository->expects($this->once())->method('findUserByEmail')->with('member@example.test')->willReturn($member);
        $repository->expects($this->once())->method('hasMembership')->with($workspace, $member)->willReturn(true);
        $repository->expects($this->never())->method('addMembership');
        $repository->expects($this->never())->method('flush');

        $service = new WorkspaceCommandService($repository, new ActivityRecorder($this->createStub(ActivityEventStore::class)));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User is already a workspace member.');

        $service->addMember($owner, $workspace, new AddWorkspaceMemberInput('member@example.test', null));
    }
}
