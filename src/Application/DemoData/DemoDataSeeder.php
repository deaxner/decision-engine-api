<?php

namespace App\Application\DemoData;

use App\Domain\Decision\Entity\ActivityEvent;
use App\Domain\Decision\Entity\DecisionOption;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\User;
use App\Domain\Decision\Entity\Vote;
use App\Domain\Decision\Entity\Workspace;
use App\Domain\Decision\Entity\WorkspaceMember;
use App\Domain\Decision\Voting\MajorityStrategy;
use App\Domain\Decision\Voting\RankedIrvStrategy;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DemoDataSeeder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MajorityStrategy $majorityStrategy,
        private readonly RankedIrvStrategy $rankedIrvStrategy,
    ) {
    }

    public function hasAnyData(): bool
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users') > 0;
    }

    public function reset(): void
    {
        $this->connection->executeStatement('TRUNCATE TABLE activity_events, session_assignees, session_results, votes, options, decision_sessions, workspace_members, workspaces, users RESTART IDENTITY CASCADE');
        $this->entityManager->clear();
    }

    public function seed(): DemoSeedReport
    {
        $password = DemoDataBlueprints::DEFAULT_PASSWORD;
        $users = $this->seedUsers($password);

        foreach (DemoDataBlueprints::WORKSPACE_BLUEPRINTS as $workspaceIndex => $workspaceBlueprint) {
            $workspaceBaseDate = (new \DateTimeImmutable('now'))->modify(sprintf('-%d days', 180 - ($workspaceIndex * 18)));
            $this->seedWorkspace($workspaceBlueprint, $users, $workspaceBaseDate);
        }

        return new DemoSeedReport(
            workspaceCount: count(DemoDataBlueprints::WORKSPACE_BLUEPRINTS),
            userCount: count(DemoDataBlueprints::USER_BLUEPRINTS),
            sessionCount: array_sum(array_map(static fn (array $workspace): int => count($workspace['sessions']), DemoDataBlueprints::WORKSPACE_BLUEPRINTS)),
            defaultPassword: $password,
        );
    }

    /**
     * @return list<User>
     */
    private function seedUsers(string $password): array
    {
        $users = [];
        $baseDate = new \DateTimeImmutable('-240 days');

        foreach (DemoDataBlueprints::USER_BLUEPRINTS as $index => $blueprint) {
            $user = new User(
                $blueprint['email'],
                '',
                $blueprint['display_name'],
            );

            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $this->setProperty($user, 'passwordHash', $hashedPassword);
            $this->setProperty($user, 'createdAt', $baseDate->modify(sprintf('+%d days', $index * 4)));

            $this->entityManager->persist($user);
            $users[] = $user;
        }

        $this->entityManager->flush();

        return $users;
    }

    /**
     * @param array{name: string, slug: string, owner: int, members: int[], sessions: array<int, array<string, mixed>>} $workspaceBlueprint
     * @param list<User> $users
     */
    private function seedWorkspace(array $workspaceBlueprint, array $users, \DateTimeImmutable $workspaceBaseDate): void
    {
        $owner = $users[$workspaceBlueprint['owner']];
        $workspace = new Workspace($workspaceBlueprint['name'], $workspaceBlueprint['slug'], $owner);
        $this->setProperty($workspace, 'createdAt', $workspaceBaseDate);
        $this->entityManager->persist($workspace);
        $this->entityManager->flush();
        $this->recordActivity(
            $workspace,
            'workspace_created',
            sprintf('%s created workspace %s.', $owner->getDisplayName(), $workspace->getName()),
            $owner,
            null,
            $workspaceBaseDate,
        );

        $members = [$owner];
        foreach ($workspaceBlueprint['members'] as $memberIndex) {
            $members[] = $users[$memberIndex];
        }

        foreach ($members as $memberPosition => $member) {
            $role = $member === $owner ? WorkspaceMember::OWNER : WorkspaceMember::MEMBER;
            $workspaceMember = new WorkspaceMember($workspace, $member, $role);
            $this->setProperty($workspaceMember, 'joinedAt', $workspaceBaseDate->modify(sprintf('+%d days', $memberPosition)));
            $this->entityManager->persist($workspaceMember);
            if ($member !== $owner) {
                $this->recordActivity(
                    $workspace,
                    'member_added',
                    sprintf('%s added %s to %s.', $owner->getDisplayName(), $member->getDisplayName(), $workspace->getName()),
                    $owner,
                    null,
                    $workspaceBaseDate->modify(sprintf('+%d days +30 minutes', $memberPosition)),
                    ['member_user_id' => (string) $member->getId()],
                );
            }
        }

        $this->entityManager->flush();

        foreach ($workspaceBlueprint['sessions'] as $sessionIndex => $sessionBlueprint) {
            $sessionBaseDate = $workspaceBaseDate->modify(sprintf('+%d days', 10 + ($sessionIndex * 9)));
            $this->seedSession($workspace, $owner, $members, $sessionBlueprint, $sessionBaseDate, $sessionIndex);
        }
    }

    /**
     * @param list<User> $members
     * @param array<string, mixed> $sessionBlueprint
     */
    private function seedSession(
        Workspace $workspace,
        User $owner,
        array $members,
        array $sessionBlueprint,
        \DateTimeImmutable $sessionBaseDate,
        int $sessionIndex,
    ): void {
        $category = DemoDataBlueprints::SESSION_CATEGORIES[$sessionIndex % count(DemoDataBlueprints::SESSION_CATEGORIES)];
        $dueAt = match ($sessionBlueprint['status']) {
            DecisionSession::OPEN => (new \DateTimeImmutable('now'))->modify(sprintf('+%d days', 2 + $sessionIndex)),
            DecisionSession::CLOSED => $sessionBaseDate->modify('+4 days'),
            default => $sessionBaseDate->modify('+14 days'),
        };

        $session = new DecisionSession(
            $workspace,
            $owner,
            $sessionBlueprint['title'],
            $sessionBlueprint['description'],
            $sessionBlueprint['type'],
            $category,
            $dueAt,
        );
        $this->setProperty($session, 'createdAt', $sessionBaseDate);

        $this->entityManager->persist($session);
        foreach (array_slice($members, 0, min(3, count($members))) as $assignee) {
            $session->assign($assignee);
        }

        $options = [];
        foreach ($sessionBlueprint['options'] as $position => $optionTitle) {
            $option = new DecisionOption($session, $optionTitle, $position + 1);
            $session->addOption($option);
            $this->entityManager->persist($option);
            $options[] = $option;
        }

        $this->entityManager->flush();
        $this->recordActivity(
            $workspace,
            'session_created',
            sprintf('%s created decision %s.', $owner->getDisplayName(), $session->getTitle()),
            $owner,
            $session,
            $sessionBaseDate,
            [
                'category' => $session->getCategory(),
                'due_at' => $session->getDueAt()?->format(\DateTimeInterface::ATOM),
                'assignee_ids' => array_map(
                    static fn ($assignee) => (string) $assignee->getUser()->getId(),
                    $session->getAssignees()->toArray(),
                ),
            ],
        );
        foreach ($options as $optionIndex => $option) {
            $this->recordActivity(
                $workspace,
                'option_added',
                sprintf('%s added option %s to %s.', $owner->getDisplayName(), $option->getTitle(), $session->getTitle()),
                $owner,
                $session,
                $sessionBaseDate->modify(sprintf('+%d minutes', 20 + ($optionIndex * 10))),
                ['option_title' => $option->getTitle()],
            );
        }

        $status = $sessionBlueprint['status'];
        if ($status === DecisionSession::OPEN || $status === DecisionSession::CLOSED) {
            $session->open();
            $this->setProperty($session, 'startsAt', $sessionBaseDate->modify('+1 day'));
            $this->recordActivity(
                $workspace,
                'voting_opened',
                sprintf('%s opened voting for %s.', $owner->getDisplayName(), $session->getTitle()),
                $owner,
                $session,
                $sessionBaseDate->modify('+1 day'),
            );
        }

        if ($status === DecisionSession::CLOSED) {
            $session->close();
            $this->setProperty($session, 'endsAt', $sessionBaseDate->modify('+4 days'));
            $this->recordActivity(
                $workspace,
                'session_closed',
                sprintf('%s closed %s.', $owner->getDisplayName(), $session->getTitle()),
                $owner,
                $session,
                $sessionBaseDate->modify('+4 days'),
            );
        }

        $this->setProperty($session, 'status', $status);
        $this->entityManager->flush();

        if ($status === DecisionSession::DRAFT) {
            return;
        }

        $voteSpecs = $this->buildVoteSpecs($session, $options, $members, $sessionBaseDate, $sessionIndex);
        $votersSeen = [];
        foreach ($voteSpecs as $voteSpec) {
            $voterId = $voteSpec['user']->getId();
            $activityType = $voterId !== null && isset($votersSeen[$voterId]) ? 'vote_changed' : 'vote_cast';
            if ($voterId !== null) {
                $votersSeen[$voterId] = true;
            }

            $vote = new Vote($session, $voteSpec['user'], $voteSpec['payload']);
            $this->setProperty($vote, 'createdAt', $voteSpec['created_at']);
            $this->entityManager->persist($vote);
            $this->recordActivity(
                $workspace,
                $activityType,
                sprintf('%s %s vote on %s.', $voteSpec['user']->getDisplayName(), $activityType === 'vote_changed' ? 'changed their' : 'cast a', $session->getTitle()),
                $voteSpec['user'],
                $session,
                $voteSpec['created_at'],
            );
            $this->entityManager->flush();

            $recomputedAt = $voteSpec['created_at']->modify('+2 minutes');
            if ($this->recomputeSnapshot($session, $recomputedAt)) {
                $this->recordActivity(
                    $workspace,
                    'result_recomputed',
                    sprintf('Results were recomputed for %s.', $session->getTitle()),
                    null,
                    $session,
                    $recomputedAt,
                );
                $this->entityManager->flush();
            }
        }

        if ($status === DecisionSession::CLOSED) {
            $recomputedAt = $sessionBaseDate->modify('+4 days +10 minutes');
            if ($this->recomputeSnapshot($session, $recomputedAt)) {
                $this->recordActivity(
                    $workspace,
                    'result_recomputed',
                    sprintf('Results were recomputed for %s.', $session->getTitle()),
                    null,
                    $session,
                    $recomputedAt,
                );
                $this->entityManager->flush();
            }
        }
    }

    /**
     * @param list<DecisionOption> $options
     * @param list<User> $members
     * @return list<array{user: User, payload: array<string, mixed>, created_at: \DateTimeImmutable}>
     */
    private function buildVoteSpecs(
        DecisionSession $session,
        array $options,
        array $members,
        \DateTimeImmutable $sessionBaseDate,
        int $sessionIndex,
    ): array {
        $activeMembers = array_slice($members, 0, min(count($members), 6));
        $voteStart = $session->getStatus() === DecisionSession::CLOSED
            ? $sessionBaseDate->modify('+1 day +2 hours')
            : new \DateTimeImmutable('-2 days');

        $voteSpecs = [];

        foreach ($activeMembers as $offset => $member) {
            if ($session->getVotingType() === DecisionSession::MAJORITY) {
                $choiceIndex = ($offset + $sessionIndex) % count($options);
                if ($offset < 3) {
                    $choiceIndex = 0;
                } elseif ($offset < 5) {
                    $choiceIndex = 1;
                }

                $voteSpecs[] = [
                    'user' => $member,
                    'payload' => $this->majorityPayload($options[$choiceIndex]),
                    'created_at' => $voteStart->modify(sprintf('+%d hours', $offset * 3)),
                ];
            } else {
                $rankingIndexes = $this->buildRankingIndexes(count($options), $offset, $sessionIndex);
                $voteSpecs[] = [
                    'user' => $member,
                    'payload' => $this->rankedPayload($options, $rankingIndexes),
                    'created_at' => $voteStart->modify(sprintf('+%d hours', $offset * 3)),
                ];
            }
        }

        $changedVoter = $activeMembers[1] ?? $activeMembers[0];
        if ($session->getVotingType() === DecisionSession::MAJORITY) {
            $voteSpecs[] = [
                'user' => $changedVoter,
                'payload' => $this->majorityPayload($options[min(2, count($options) - 1)]),
                'created_at' => $voteStart->modify('+20 hours'),
            ];
        } else {
            $voteSpecs[] = [
                'user' => $changedVoter,
                'payload' => $this->rankedPayload($options, array_reverse(range(0, count($options) - 1))),
                'created_at' => $voteStart->modify('+20 hours'),
            ];
        }

        return $voteSpecs;
    }

    /**
     * @return list<int>
     */
    private function buildRankingIndexes(int $optionCount, int $offset, int $sessionIndex): array
    {
        $indexes = range(0, $optionCount - 1);
        $rotation = ($offset + $sessionIndex) % $optionCount;

        return array_merge(array_slice($indexes, $rotation), array_slice($indexes, 0, $rotation));
    }

    private function majorityPayload(DecisionOption $option): array
    {
        return [
            'version' => 1,
            'type' => DecisionSession::MAJORITY,
            'data' => [
                'choice' => (string) $option->getId(),
            ],
        ];
    }

    /**
     * @param list<DecisionOption> $options
     * @param list<int> $rankingIndexes
     */
    private function rankedPayload(array $options, array $rankingIndexes): array
    {
        $ranking = [];
        foreach ($rankingIndexes as $rankingIndex) {
            $ranking[] = (string) $options[$rankingIndex]->getId();
        }

        return [
            'version' => 1,
            'type' => DecisionSession::RANKED_IRV,
            'data' => [
                'ranking' => $ranking,
            ],
        ];
    }

    private function recomputeSnapshot(DecisionSession $session, \DateTimeImmutable $calculatedAt): bool
    {
        $votes = $this->entityManager->getRepository(Vote::class)->findBy(
            ['session' => $session],
            ['createdAt' => 'DESC', 'id' => 'DESC']
        );

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
            'computed_at' => $calculatedAt->format(\DateTimeInterface::ATOM),
        ];

        /** @var SessionResult|null $result */
        $result = $this->entityManager->getRepository(SessionResult::class)->find($session);
        $winningOption = $computed['winner'] ? ($optionById[$computed['winner']] ?? null) : null;

        if ($result === null) {
            $result = new SessionResult($session);
            $result->update($winningOption, $resultData);
            $this->setProperty($result, 'calculatedAt', $calculatedAt);
            $this->entityManager->persist($result);
            $this->entityManager->flush();

            return true;
        }

        if ($result->matches($computed['winner'], $resultData)) {
            return false;
        }

        $result->update($winningOption, $resultData);
        $this->setProperty($result, 'calculatedAt', $calculatedAt);
        $this->entityManager->flush();

        return true;
    }

    private function recordActivity(
        Workspace $workspace,
        string $type,
        string $summary,
        ?User $actor,
        ?DecisionSession $session,
        \DateTimeImmutable $createdAt,
        array $metadata = [],
    ): void {
        $event = new ActivityEvent($workspace, $type, $summary, $actor, $session, $metadata);
        $this->setProperty($event, 'createdAt', $createdAt);
        $this->entityManager->persist($event);
    }

    private function setProperty(object $entity, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($entity, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $value);
    }
}
