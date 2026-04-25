<?php

namespace App\Tests\Unit\Decision;

use App\Application\Decision\WorkspaceAccess;
use App\Application\Decision\WorkspaceMembershipRepository;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;
use PHPUnit\Framework\TestCase;

final class WorkspaceAccessTest extends TestCase
{
    public function testRequireMemberReturnsMembershipFromRepositoryPort(): void
    {
        $owner = new User('owner@example.test', 'hash', 'Owner');
        $workspace = new Workspace('Product', 'product', $owner);
        $memberUser = new User('member@example.test', 'hash', 'Member');
        $membership = new WorkspaceMember($workspace, $memberUser, WorkspaceMember::MEMBER);

        $repository = $this->createMock(WorkspaceMembershipRepository::class);
        $repository->expects($this->once())
            ->method('findMembership')
            ->with($memberUser, $workspace)
            ->willReturn($membership);

        $access = new WorkspaceAccess($repository);

        self::assertSame($membership, $access->requireMember($memberUser, $workspace));
    }

    public function testRequireMemberThrowsWhenRepositoryFindsNothing(): void
    {
        $user = new User('member@example.test', 'hash', 'Member');
        $workspace = new Workspace('Product', 'product', $user);

        $repository = $this->createMock(WorkspaceMembershipRepository::class);
        $repository->expects($this->once())
            ->method('findMembership')
            ->with($user, $workspace)
            ->willReturn(null);

        $access = new WorkspaceAccess($repository);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User is not a workspace member.');

        $access->requireMember($user, $workspace);
    }
}
