<?php

namespace App\Application\Decision;

use App\Application\Decision\Input\AddOptionInput;
use App\Application\Decision\Input\CreateSessionInput;
use App\Application\Decision\Input\UpdateSessionStatusInput;
use App\Application\Decision\Message\RecomputeSessionResult;
use App\Domain\Decision\Entity\DecisionOption;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionAssignee;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Workspace;
use Symfony\Component\Messenger\MessageBusInterface;

final class SessionCommandService
{
    public function __construct(
        private readonly SessionCommandRepository $repository,
        private readonly ActivityRecorder $activity,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function createSession(User $actor, Workspace $workspace, CreateSessionInput $input): DecisionSession
    {
        $session = new DecisionSession($workspace, $actor, $input->title, $input->description, $input->votingType, $input->category, $input->dueAt);
        foreach ($this->assigneesFromInput($input, $workspace) as $assignee) {
            $session->assign($assignee);
        }
        $this->repository->addSession($session);
        $this->activity->record(
            $workspace,
            'session_created',
            sprintf('%s created decision %s.', $actor->getDisplayName(), $session->getTitle()),
            $actor,
            $session,
            [
                'category' => $session->getCategory(),
                'due_at' => $session->getDueAt()?->format(\DateTimeInterface::ATOM),
                'assignee_ids' => array_map(fn (SessionAssignee $assignee) => (string) $assignee->getUser()->getId(), $session->getAssignees()->toArray()),
            ],
        );
        $this->repository->flush();

        return $session;
    }

    public function addOption(User $actor, DecisionSession $session, AddOptionInput $input): DecisionOption
    {
        $position = $input->position ?? $session->getOptions()->count() + 1;
        $option = new DecisionOption($session, $input->title, $position);
        $session->addOption($option);
        $this->repository->addOption($option);
        $this->activity->record(
            $session->getWorkspace(),
            'option_added',
            sprintf('%s added option %s to %s.', $actor->getDisplayName(), $option->getTitle(), $session->getTitle()),
            $actor,
            $session,
            ['option_title' => $option->getTitle()],
        );
        $this->repository->flush();

        return $option;
    }

    public function updateStatus(User $actor, DecisionSession $session, UpdateSessionStatusInput $input): DecisionSession
    {
        if ($input->status === DecisionSession::OPEN) {
            $session->open();
            $this->activity->record(
                $session->getWorkspace(),
                'voting_opened',
                sprintf('%s opened voting for %s.', $actor->getDisplayName(), $session->getTitle()),
                $actor,
                $session,
            );
        } elseif ($input->status === DecisionSession::CLOSED) {
            $session->close();
            $this->activity->record(
                $session->getWorkspace(),
                'session_closed',
                sprintf('%s closed %s.', $actor->getDisplayName(), $session->getTitle()),
                $actor,
                $session,
            );
        } else {
            throw new \DomainException('Unsupported session status transition.');
        }

        $this->repository->flush();
        if ($input->status === DecisionSession::CLOSED) {
            $this->bus->dispatch(new RecomputeSessionResult((int) $session->getId(), 'session_closed'));
        }

        return $session;
    }

    /**
     * @return list<User>
     */
    private function assigneesFromInput(CreateSessionInput $input, Workspace $workspace): array
    {
        $assigneeIds = $input->assigneeIds;
        if ($assigneeIds === []) {
            return [];
        }

        $assignees = [];
        foreach ($assigneeIds as $assigneeId) {
            if (!is_numeric($assigneeId)) {
                throw new \DomainException('Assignee id must be numeric.');
            }
            $assignee = $this->repository->findUserById((int) $assigneeId);
            if (!$assignee instanceof User) {
                throw new \DomainException('Assignee user not found.');
            }
            if (!$this->repository->hasWorkspaceMembership($workspace, $assignee)) {
                throw new \DomainException('Assignee must be a workspace member.');
            }
            $assignees[] = $assignee;
        }

        return $assignees;
    }
}
