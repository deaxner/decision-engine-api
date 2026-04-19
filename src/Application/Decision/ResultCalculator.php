<?php

namespace App\Application\Decision;

use App\Domain\Decision\Entity\DecisionOption;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\Vote;
use App\Domain\Decision\Voting\MajorityStrategy;
use App\Domain\Decision\Voting\RankedIrvStrategy;
use Doctrine\ORM\EntityManagerInterface;

final class ResultCalculator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MajorityStrategy $majorityStrategy,
        private readonly RankedIrvStrategy $rankedIrvStrategy,
        private readonly ResultUpdatedPublisher $publisher,
        private readonly ActivityRecorder $activity,
    ) {
    }

    public function recompute(DecisionSession $session): SessionResult
    {
        $votes = $this->entityManager->getRepository(Vote::class)->findBy(['session' => $session], ['createdAt' => 'DESC', 'id' => 'DESC']);
        $latestByUser = [];
        foreach ($votes as $vote) {
            $userId = $vote->getUser()->getId();
            if ($userId !== null && !isset($latestByUser[$userId])) {
                $latestByUser[$userId] = $vote->getPayload();
            }
        }

        $optionPositions = [];
        $optionById = [];
        foreach ($session->getOptions() as $option) {
            if ($option->getId() !== null) {
                $optionPositions[$option->getId()] = $option->getPosition();
                $optionById[$option->getId()] = $option;
            }
        }

        $computed = $session->getVotingType() === DecisionSession::MAJORITY
            ? $this->majorityStrategy->compute($optionPositions, array_values($latestByUser))
            : $this->rankedIrvStrategy->compute($optionPositions, array_values($latestByUser));

        $resultData = [
            'winner' => $computed['winner'] ? (string) $computed['winner'] : null,
            'rounds' => $computed['rounds'],
            'total_votes' => $computed['total_votes'],
            'computed_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        $result = $this->entityManager->getRepository(SessionResult::class)->find($session) ?? new SessionResult($session);
        if ($result->getVersion() > 0 && $result->matches($computed['winner'], $resultData)) {
            return $result;
        }

        $result->update($computed['winner'] ? $optionById[$computed['winner']] : null, $resultData);
        $this->entityManager->persist($result);
        $this->activity->record(
            $session->getWorkspace(),
            'result_recomputed',
            sprintf('Results were recomputed for %s.', $session->getTitle()),
            null,
            $session,
            ['version' => $result->getVersion()],
        );
        $this->entityManager->flush();
        $this->publisher->publish($result);

        return $result;
    }
}
