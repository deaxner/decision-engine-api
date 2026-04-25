<?php

namespace App\Application\Decision;

use App\Application\Decision\Input\AddWorkspaceMemberInput;
use App\Application\Decision\Input\CreateWorkspaceInput;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;

final class WorkspaceCommandService
{
    public function __construct(
        private readonly WorkspaceCommandRepository $repository,
        private readonly ActivityRecorder $activity,
    ) {
    }

    public function createWorkspace(User $user, CreateWorkspaceInput $input): Workspace
    {
        $workspace = new Workspace($input->name, $input->slug, $user);
        $this->repository->addWorkspace($workspace);
        $this->repository->addMembership(new WorkspaceMember($workspace, $user, WorkspaceMember::OWNER));
        $this->activity->record($workspace, 'workspace_created', sprintf('%s created workspace %s.', $user->getDisplayName(), $workspace->getName()), $user);
        $this->repository->flush();

        return $workspace;
    }

    public function addMember(User $actor, Workspace $workspace, AddWorkspaceMemberInput $input): WorkspaceMember
    {
        $memberUser = null;
        if ($input->email !== null) {
            $memberUser = $this->repository->findUserByEmail($input->email);
        } elseif ($input->userId !== null) {
            $memberUser = $this->repository->findUserById($input->userId);
        }
        if (!$memberUser instanceof User) {
            throw new \DomainException('Member user not found.');
        }
        if ($this->repository->hasMembership($workspace, $memberUser)) {
            throw new \DomainException('User is already a workspace member.');
        }

        $member = new WorkspaceMember($workspace, $memberUser, WorkspaceMember::MEMBER);
        $this->repository->addMembership($member);
        $this->activity->record(
            $workspace,
            'member_added',
            sprintf('%s added %s to %s.', $actor->getDisplayName(), $memberUser->getDisplayName(), $workspace->getName()),
            $actor,
            null,
            ['member_user_id' => (string) $memberUser->getId()],
        );
        $this->repository->flush();

        return $member;
    }
}
