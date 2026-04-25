<?php

namespace App\Application\Decision;

use App\Application\Decision\Output\DecisionOptionOutput;
use App\Application\Decision\Output\SessionAssigneeOutput;
use App\Application\Decision\Output\SessionOutput;
use App\Domain\Decision\Entity\DecisionOption;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionAssignee;

final class SessionViewFactory
{
    public function payload(DecisionSession $session): SessionOutput
    {
        return new SessionOutput(
            id: (string) $session->getId(),
            title: $session->getTitle(),
            description: $session->getDescription(),
            category: $session->getCategory(),
            status: $session->getStatus(),
            votingType: $session->getVotingType(),
            dueAt: $session->getDueAt()?->format(\DateTimeInterface::ATOM),
            startsAt: $session->getStartsAt()?->format(\DateTimeInterface::ATOM),
            endsAt: $session->getEndsAt()?->format(\DateTimeInterface::ATOM),
            assignees: array_map(fn (SessionAssignee $assignee) => $this->assigneePayload($assignee), $session->getAssignees()->toArray()),
            options: array_map(fn (DecisionOption $option) => $this->optionPayload($option), $session->getOptions()->toArray()),
        );
    }

    public function optionPayload(DecisionOption $option): DecisionOptionOutput
    {
        return new DecisionOptionOutput(
            id: (string) $option->getId(),
            title: $option->getTitle(),
            position: $option->getPosition(),
        );
    }

    private function assigneePayload(SessionAssignee $assignee): SessionAssigneeOutput
    {
        $user = $assignee->getUser();

        return new SessionAssigneeOutput(
            id: (string) $user->getId(),
            displayName: $user->getDisplayName(),
            email: $user->getEmail(),
        );
    }
}
