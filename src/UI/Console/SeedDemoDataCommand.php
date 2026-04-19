<?php

namespace App\UI\Console;

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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed:demo-data',
    description: 'Seed demo workspaces, users, sessions, options, votes, and result snapshots.',
)]
final class SeedDemoDataCommand extends Command
{
    private const DEFAULT_PASSWORD = 'Decision123!';

    /** @var array<int, array{name: string, slug: string, owner: int, members: int[], sessions: array<int, array<string, mixed>>}> */
    private const WORKSPACE_BLUEPRINTS = [
        [
            'name' => 'Platform Council',
            'slug' => 'platform-council',
            'owner' => 0,
            'members' => [1, 2, 3, 4, 5, 6],
            'sessions' => [
                [
                    'title' => 'Choose the default voting method for new sessions',
                    'description' => 'Baseline product decision for new workspace setup.',
                    'type' => DecisionSession::MAJORITY,
                    'status' => DecisionSession::CLOSED,
                    'options' => ['Majority', 'Ranked IRV', 'Per-workspace choice'],
                ],
                [
                    'title' => 'Pick the initial Mercure topic granularity',
                    'description' => 'Balance simplicity against targeted updates.',
                    'type' => DecisionSession::RANKED_IRV,
                    'status' => DecisionSession::CLOSED,
                    'options' => ['Per session', 'Per workspace', 'Single global topic'],
                ],
                [
                    'title' => 'Set the current JWT expiry window',
                    'description' => 'Open product decision with active votes.',
                    'type' => DecisionSession::MAJORITY,
                    'status' => DecisionSession::OPEN,
                    'options' => ['4 hours', '12 hours', '24 hours'],
                ],
                [
                    'title' => 'Define the first session template pack',
                    'description' => 'Still drafting the initial reusable flows.',
                    'type' => DecisionSession::RANKED_IRV,
                    'status' => DecisionSession::DRAFT,
                    'options' => ['Product', 'Engineering', 'Operations'],
                ],
            ],
        ],
        [
            'name' => 'Growth Studio',
            'slug' => 'growth-studio',
            'owner' => 1,
            'members' => [0, 2, 3, 6, 7, 8],
            'sessions' => [
                [
                    'title' => 'Choose the onboarding landing experience',
                    'description' => 'Historic decision for first-run product flow.',
                    'type' => DecisionSession::MAJORITY,
                    'status' => DecisionSession::CLOSED,
                    'options' => ['Guided checklist', 'Sample workspace', 'Blank workspace'],
                ],
                [
                    'title' => 'Prioritize the first notification digest cadence',
                    'description' => 'Historic ranked decision about reminder frequency.',
                    'type' => DecisionSession::RANKED_IRV,
                    'status' => DecisionSession::CLOSED,
                    'options' => ['Daily', 'Twice weekly', 'Weekly'],
                ],
                [
                    'title' => 'Pick the current invite acceptance flow',
                    'description' => 'Open session with a changed vote already captured.',
                    'type' => DecisionSession::MAJORITY,
                    'status' => DecisionSession::OPEN,
                    'options' => ['Email magic link', 'Password first', 'Admin confirmation'],
                ],
                [
                    'title' => 'Draft the public changelog format',
                    'description' => 'Under discussion but not opened yet.',
                    'type' => DecisionSession::RANKED_IRV,
                    'status' => DecisionSession::DRAFT,
                    'options' => ['Release notes', 'Timeline feed', 'Digest cards'],
                ],
            ],
        ],
        [
            'name' => 'Operations Guild',
            'slug' => 'operations-guild',
            'owner' => 2,
            'members' => [0, 1, 4, 5, 7, 9],
            'sessions' => [
                [
                    'title' => 'Decide the default close-session rule',
                    'description' => 'Historic governance choice for session ownership.',
                    'type' => DecisionSession::MAJORITY,
                    'status' => DecisionSession::CLOSED,
                    'options' => ['Owner only', 'Owner or admin', 'Any member'],
                ],
                [
                    'title' => 'Rank the first audit export format',
                    'description' => 'Historic ranking of export output priorities.',
                    'type' => DecisionSession::RANKED_IRV,
                    'status' => DecisionSession::CLOSED,
                    'options' => ['CSV', 'JSON', 'PDF'],
                ],
                [
                    'title' => 'Choose the current worker retry policy',
                    'description' => 'Open infra decision with active votes.',
                    'type' => DecisionSession::MAJORITY,
                    'status' => DecisionSession::OPEN,
                    'options' => ['3 retries', '5 retries', 'Exponential backoff only'],
                ],
                [
                    'title' => 'Draft the reset-demo-data safeguard',
                    'description' => 'Draft decision around safe local tooling.',
                    'type' => DecisionSession::RANKED_IRV,
                    'status' => DecisionSession::DRAFT,
                    'options' => ['Confirm prompt', 'Environment lock', 'Owner-only command'],
                ],
            ],
        ],
        [
            'name' => 'Product Research',
            'slug' => 'product-research',
            'owner' => 3,
            'members' => [0, 1, 5, 6, 8, 9],
            'sessions' => [
                [
                    'title' => 'Choose the first mobile navigation pattern',
                    'description' => 'Historic UX decision for the web client.',
                    'type' => DecisionSession::MAJORITY,
                    'status' => DecisionSession::CLOSED,
                    'options' => ['Bottom tabs', 'Side rail', 'Segmented header'],
                ],
                [
                    'title' => 'Rank the initial ranked-result explanation layout',
                    'description' => 'Historic presentation decision for IRV rounds.',
                    'type' => DecisionSession::RANKED_IRV,
                    'status' => DecisionSession::CLOSED,
                    'options' => ['Round timeline', 'Matrix table', 'Narrative summary'],
                ],
                [
                    'title' => 'Pick the current password policy',
                    'description' => 'Open security and UX tradeoff.',
                    'type' => DecisionSession::MAJORITY,
                    'status' => DecisionSession::OPEN,
                    'options' => ['8 chars min', '12 chars min', '12 chars + symbol'],
                ],
                [
                    'title' => 'Draft the dashboard empty state',
                    'description' => 'Drafting the first workspace shell experience.',
                    'type' => DecisionSession::RANKED_IRV,
                    'status' => DecisionSession::DRAFT,
                    'options' => ['Checklist', 'Starter cards', 'Compact prompt'],
                ],
            ],
        ],
        [
            'name' => 'Delivery Systems',
            'slug' => 'delivery-systems',
            'owner' => 4,
            'members' => [1, 2, 3, 7, 8, 9],
            'sessions' => [
                [
                    'title' => 'Choose the default Docker workflow',
                    'description' => 'Historic developer-experience decision.',
                    'type' => DecisionSession::MAJORITY,
                    'status' => DecisionSession::CLOSED,
                    'options' => ['Compose only', 'Host tools only', 'Hybrid workflow'],
                ],
                [
                    'title' => 'Rank the first observability slice',
                    'description' => 'Historic prioritization of operational visibility.',
                    'type' => DecisionSession::RANKED_IRV,
                    'status' => DecisionSession::CLOSED,
                    'options' => ['Worker health', 'Vote throughput', 'Mercure activity'],
                ],
                [
                    'title' => 'Pick the current API error envelope',
                    'description' => 'Open API consistency decision with re-votes.',
                    'type' => DecisionSession::MAJORITY,
                    'status' => DecisionSession::OPEN,
                    'options' => ['Flat errors', 'RFC 7807', 'Custom code + details'],
                ],
                [
                    'title' => 'Draft the workspace archive policy',
                    'description' => 'Not opened yet.',
                    'type' => DecisionSession::RANKED_IRV,
                    'status' => DecisionSession::DRAFT,
                    'options' => ['Soft archive', 'Hard archive', 'Archive with export'],
                ],
            ],
        ],
    ];

    /** @var array<int, array<email: string, display_name: string>> */
    private const USER_BLUEPRINTS = [
        ['email' => 'alex@demo.local', 'display_name' => 'Alex Mercer'],
        ['email' => 'bianca@demo.local', 'display_name' => 'Bianca Vos'],
        ['email' => 'carlos@demo.local', 'display_name' => 'Carlos Tan'],
        ['email' => 'dina@demo.local', 'display_name' => 'Dina Smit'],
        ['email' => 'emma@demo.local', 'display_name' => 'Emma Cole'],
        ['email' => 'farid@demo.local', 'display_name' => 'Farid Noor'],
        ['email' => 'gia@demo.local', 'display_name' => 'Gia Vermeer'],
        ['email' => 'hugo@demo.local', 'display_name' => 'Hugo Dean'],
        ['email' => 'ines@demo.local', 'display_name' => 'Ines Bakker'],
        ['email' => 'jules@demo.local', 'display_name' => 'Jules Hart'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MajorityStrategy $majorityStrategy,
        private readonly RankedIrvStrategy $rankedIrvStrategy,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('reset', null, InputOption::VALUE_NONE, 'Truncate decision tables before seeding.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->hasAnyData() && !$input->getOption('reset')) {
            $io->error('Database already contains data. Re-run with --reset to seed this demo dataset from scratch.');

            return Command::FAILURE;
        }

        if ($input->getOption('reset')) {
            $this->truncateDecisionTables();
            $this->entityManager->clear();
        }

        $password = self::DEFAULT_PASSWORD;
        $users = $this->seedUsers($password);

        foreach (self::WORKSPACE_BLUEPRINTS as $workspaceIndex => $workspaceBlueprint) {
            $workspaceBaseDate = (new \DateTimeImmutable('now'))->modify(sprintf('-%d days', 180 - ($workspaceIndex * 18)));
            $this->seedWorkspace($workspaceBlueprint, $users, $workspaceBaseDate);
        }

        $io->success('Seeded 5 workspaces, 10 users, and 20 decision sessions.');
        $io->writeln(sprintf('Default demo password for all users: <info>%s</info>', $password));
        $io->writeln('Run this again with <info>--reset</info> to recreate the dataset from scratch.');

        return Command::SUCCESS;
    }

    private function hasAnyData(): bool
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users') > 0;
    }

    private function truncateDecisionTables(): void
    {
        $this->connection->executeStatement('TRUNCATE TABLE activity_events, session_results, votes, options, decision_sessions, workspace_members, workspaces, users RESTART IDENTITY CASCADE');
    }

    /**
     * @return list<User>
     */
    private function seedUsers(string $password): array
    {
        $users = [];
        $baseDate = new \DateTimeImmutable('-240 days');

        foreach (self::USER_BLUEPRINTS as $index => $blueprint) {
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
        $session = new DecisionSession(
            $workspace,
            $owner,
            $sessionBlueprint['title'],
            $sessionBlueprint['description'],
            $sessionBlueprint['type'],
        );
        $this->setProperty($session, 'createdAt', $sessionBaseDate);

        $this->entityManager->persist($session);

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
