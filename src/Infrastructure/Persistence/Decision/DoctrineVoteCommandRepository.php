<?php

namespace App\Infrastructure\Persistence\Decision;

use App\Application\Decision\VoteCommandRepository;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Vote;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineVoteCommandRepository implements VoteCommandRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findExistingVote(DecisionSession $session, User $user): ?Vote
    {
        $vote = $this->entityManager->getRepository(Vote::class)->findOneBy([
            'session' => $session,
            'user' => $user,
        ]);

        return $vote instanceof Vote ? $vote : null;
    }

    public function addVote(Vote $vote): void
    {
        $this->entityManager->persist($vote);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
