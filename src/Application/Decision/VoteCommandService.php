<?php

namespace App\Application\Decision;

use App\Application\Decision\Input\CastVoteInput;
use App\Application\Decision\Message\VoteCastEvent;
use App\Domain\Decision\Entity\DecisionOption;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Vote;
use Symfony\Component\Messenger\MessageBusInterface;

final class VoteCommandService
{
    public function __construct(
        private readonly VoteCommandRepository $repository,
        private readonly VotePayloadValidator $validator,
        private readonly ActivityRecorder $activity,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function castVote(User $user, DecisionSession $session, CastVoteInput $input): Vote
    {
        if ($session->getStatus() !== DecisionSession::OPEN) {
            throw new \DomainException('Votes can only be cast while the session is open.');
        }

        $optionIds = array_values(array_filter(array_map(fn (DecisionOption $option) => $option->getId(), $session->getOptions()->toArray())));
        $this->validator->validate($session->getVotingType(), $input->payload, $optionIds);
        $existingVote = $this->repository->findExistingVote($session, $user);
        $activityType = $existingVote instanceof Vote ? 'vote_changed' : 'vote_cast';

        $vote = new Vote($session, $user, $input->payload);
        $this->repository->addVote($vote);
        $this->activity->record(
            $session->getWorkspace(),
            $activityType,
            sprintf('%s %s vote on %s.', $user->getDisplayName(), $activityType === 'vote_changed' ? 'changed their' : 'cast a', $session->getTitle()),
            $user,
            $session,
        );
        $this->repository->flush();
        $this->bus->dispatch(new VoteCastEvent((int) $session->getId(), (int) $vote->getId()));

        return $vote;
    }
}
