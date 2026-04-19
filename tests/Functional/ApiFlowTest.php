<?php

namespace App\Tests\Functional;

use App\Application\Decision\Handler\VoteCastEventHandler;
use App\Application\Decision\Message\VoteCastEvent;
use App\Domain\Decision\Entity\DecisionSession;
use App\Domain\Decision\Entity\SessionResult;
use App\Domain\Decision\Entity\Vote;
use App\Tests\Infrastructure\Mercure\RecordingResultUpdatedPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[RunTestsInSeparateProcesses]
final class ApiFlowTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $connection = $entityManager->getConnection();
        $isPostgres = str_contains($connection->getDatabasePlatform()::class, 'PostgreSQL');
        if ($isPostgres) {
            $connection->executeStatement('DROP SCHEMA public CASCADE');
            $connection->executeStatement('CREATE SCHEMA public');
        }
        $tool = new SchemaTool($entityManager);
        if (!$isPostgres) {
            $tool->dropSchema($metadata);
        }
        $tool->createSchema($metadata);
        RecordingResultUpdatedPublisher::reset();
        self::ensureKernelShutdown();
    }

    public function testCoreDecisionFlowAndImmutableVotes(): void
    {
        $client = static::createClient();
        $owner = $this->register($client, 'owner@example.test');

        $client->request('POST', '/workspaces', server: $this->auth($owner['token']), content: json_encode([
            'name' => 'Product',
            'slug' => 'product',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $workspace = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->request('POST', '/workspaces/'.$workspace['id'].'/sessions', server: $this->auth($owner['token']), content: json_encode([
            'title' => 'Choose roadmap item',
            'voting_type' => 'MAJORITY',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $session = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->request('POST', '/sessions/'.$session['id'].'/options', server: $this->auth($owner['token']), content: json_encode(['title' => 'A'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $optionA = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->request('PATCH', '/sessions/'.$session['id'], server: $this->auth($owner['token']), content: json_encode(['status' => 'OPEN'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);

        $client->request('POST', '/sessions/'.$session['id'].'/options', server: $this->auth($owner['token']), content: json_encode(['title' => 'B'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $optionB = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->request('PATCH', '/sessions/'.$session['id'], server: $this->auth($owner['token']), content: json_encode(['status' => 'OPEN'], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $client->request('POST', '/sessions/'.$session['id'].'/options', server: $this->auth($owner['token']), content: json_encode(['title' => 'C'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);

        $firstVote = $this->castMajorityVote($client, $owner['token'], $session['id'], $optionA['id']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertCount(1, $entityManager->getRepository(Vote::class)->findAll());
        self::assertNull($this->resultFor($session['id']));
        self::assertSame([], RecordingResultUpdatedPublisher::published());

        $this->handleVoteCast($session['id'], $firstVote['vote_id']);
        $firstResult = $this->resultFor($session['id']);
        self::assertInstanceOf(SessionResult::class, $firstResult);
        self::assertSame(1, $firstResult->getVersion());
        self::assertSame($optionA['id'], $firstResult->toArray()['winning_option_id']);
        self::assertCount(1, RecordingResultUpdatedPublisher::published());

        $this->handleVoteCast($session['id'], $firstVote['vote_id']);
        $duplicateResult = $this->resultFor($session['id']);
        self::assertSame(1, $duplicateResult?->getVersion());
        self::assertCount(1, RecordingResultUpdatedPublisher::published());

        $secondVote = $this->castMajorityVote($client, $owner['token'], $session['id'], $optionB['id']);
        self::assertSame(1, $this->resultFor($session['id'])?->getVersion());
        $this->handleVoteCast($session['id'], $secondVote['vote_id']);

        $client->request('GET', '/sessions/'.$session['id'].'/results', server: $this->auth($owner['token']));
        self::assertResponseIsSuccessful();
        $result = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $result['version']);
        self::assertSame($optionB['id'], $result['winning_option_id']);
        self::assertSame(1, $result['result_data']['total_votes']);
        self::assertCount(2, RecordingResultUpdatedPublisher::published());

        $this->handleVoteCast($session['id'], $secondVote['vote_id']);
        self::assertSame(2, $this->resultFor($session['id'])?->getVersion());
        self::assertCount(2, RecordingResultUpdatedPublisher::published());

        self::assertCount(2, $entityManager->getRepository(Vote::class)->findAll());
    }

    public function testPayloadValidation(): void
    {
        $client = static::createClient();
        $owner = $this->register($client, 'validation@example.test');
        [$sessionId, $optionId] = $this->openSession($client, $owner['token'], 'MAJORITY');

        $client->request('POST', '/sessions/'.$sessionId.'/votes', server: $this->auth($owner['token']), content: json_encode([
            'version' => 1,
            'type' => 'RANKED_IRV',
            'data' => ['ranking' => [$optionId]],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(400);
    }

    public function testWorkspaceReadModelAndEmailMembers(): void
    {
        $client = static::createClient();
        $owner = $this->register($client, 'owner-read@example.test');
        $member = $this->register($client, 'member-read@example.test');
        $outsider = $this->register($client, 'outsider-read@example.test');

        $client->request('POST', '/workspaces', server: $this->auth($owner['token']), content: json_encode([
            'name' => 'Research',
            'slug' => 'research',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $workspace = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('OWNER', $workspace['role']);
        self::assertSame(1, $workspace['member_count']);
        self::assertSame(['total' => 0, 'draft' => 0, 'open' => 0, 'closed' => 0], $workspace['session_counts']);
        self::assertSame(0, $workspace['participation_rate']);

        $client->request('GET', '/workspaces', server: $this->auth($owner['token']));
        self::assertResponseIsSuccessful();
        $ownerWorkspaces = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $ownerWorkspaces);
        self::assertSame($workspace['id'], $ownerWorkspaces[0]['id']);
        self::assertSame('OWNER', $ownerWorkspaces[0]['role']);
        self::assertSame(1, $ownerWorkspaces[0]['member_count']);

        $client->request('GET', '/workspaces/'.$workspace['id'], server: $this->auth($outsider['token']));
        self::assertResponseStatusCodeSame(400);

        $client->request('POST', '/workspaces/'.$workspace['id'].'/members', server: $this->auth($owner['token']), content: json_encode([
            'email' => $member['user']['email'],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $membership = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($member['user']['id'], $membership['user_id']);
        self::assertSame('MEMBER', $membership['role']);

        $client->request('POST', '/workspaces/'.$workspace['id'].'/members', server: $this->auth($owner['token']), content: json_encode([
            'email' => $member['user']['email'],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);

        $client->request('POST', '/workspaces/'.$workspace['id'].'/members', server: $this->auth($owner['token']), content: json_encode([
            'email' => 'missing@example.test',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);

        $client->request('GET', '/workspaces', server: $this->auth($member['token']));
        self::assertResponseIsSuccessful();
        $memberWorkspaces = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $memberWorkspaces);
        self::assertSame('MEMBER', $memberWorkspaces[0]['role']);
        self::assertSame(2, $memberWorkspaces[0]['member_count']);

        $client->request('GET', '/workspaces/'.$workspace['id'], server: $this->auth($member['token']));
        self::assertResponseIsSuccessful();
        $workspaceDetail = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($workspace['id'], $workspaceDetail['id']);
        self::assertSame('MEMBER', $workspaceDetail['role']);
        self::assertSame(2, $workspaceDetail['member_count']);
        self::assertSame(0, $workspaceDetail['participation_rate']);
    }

    public function testSessionReadModel(): void
    {
        $client = static::createClient();
        $owner = $this->register($client, 'session-read@example.test');

        $client->request('POST', '/workspaces', server: $this->auth($owner['token']), content: json_encode([
            'name' => 'Product',
            'slug' => 'product-read',
        ], JSON_THROW_ON_ERROR));
        $workspace = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->request('POST', '/workspaces/'.$workspace['id'].'/sessions', server: $this->auth($owner['token']), content: json_encode([
            'title' => 'Choose launch plan',
            'description' => 'Pick the launch plan for Q2.',
            'voting_type' => 'RANKED_IRV',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $session = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->request('POST', '/sessions/'.$session['id'].'/options', server: $this->auth($owner['token']), content: json_encode(['title' => 'Second', 'position' => 2], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $client->request('POST', '/sessions/'.$session['id'].'/options', server: $this->auth($owner['token']), content: json_encode(['title' => 'First', 'position' => 1], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        $client->request('GET', '/workspaces/'.$workspace['id'].'/sessions', server: $this->auth($owner['token']));
        self::assertResponseIsSuccessful();
        $sessions = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $sessions);
        self::assertSame($session['id'], $sessions[0]['id']);
        self::assertSame('Choose launch plan', $sessions[0]['title']);
        self::assertSame('RANKED_IRV', $sessions[0]['voting_type']);

        $client->request('GET', '/workspaces/'.$workspace['id'], server: $this->auth($owner['token']));
        self::assertResponseIsSuccessful();
        $workspaceDetail = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['total' => 1, 'draft' => 1, 'open' => 0, 'closed' => 0], $workspaceDetail['session_counts']);

        $client->request('GET', '/sessions/'.$session['id'], server: $this->auth($owner['token']));
        self::assertResponseIsSuccessful();
        $detail = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Choose launch plan', $detail['title']);
        self::assertSame('Pick the launch plan for Q2.', $detail['description']);
        self::assertSame('DRAFT', $detail['status']);
        self::assertNull($detail['starts_at']);
        self::assertNull($detail['ends_at']);
        self::assertSame(['First', 'Second'], array_column($detail['options'], 'title'));
    }

    private function register($client, string $email): array
    {
        $client->request('POST', '/register', content: json_encode([
            'email' => $email,
            'password' => 'secret-password',
            'display_name' => 'Test User',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        return json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function openSession($client, string $token, string $votingType): array
    {
        $client->request('POST', '/workspaces', server: $this->auth($token), content: json_encode(['name' => uniqid('Workspace '), 'slug' => uniqid('workspace-')], JSON_THROW_ON_ERROR));
        $workspace = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->request('POST', '/workspaces/'.$workspace['id'].'/sessions', server: $this->auth($token), content: json_encode(['title' => 'Decision', 'voting_type' => $votingType], JSON_THROW_ON_ERROR));
        $session = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->request('POST', '/sessions/'.$session['id'].'/options', server: $this->auth($token), content: json_encode(['title' => 'A'], JSON_THROW_ON_ERROR));
        $optionA = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->request('POST', '/sessions/'.$session['id'].'/options', server: $this->auth($token), content: json_encode(['title' => 'B'], JSON_THROW_ON_ERROR));
        $client->request('PATCH', '/sessions/'.$session['id'], server: $this->auth($token), content: json_encode(['status' => 'OPEN'], JSON_THROW_ON_ERROR));

        return [$session['id'], $optionA['id']];
    }

    private function castMajorityVote($client, string $token, string $sessionId, string $optionId): array
    {
        $client->request('POST', '/sessions/'.$sessionId.'/votes', server: $this->auth($token), content: json_encode([
            'version' => 1,
            'type' => 'MAJORITY',
            'data' => ['choice' => $optionId],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(202);
        $body = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($sessionId, $body['session_id']);
        self::assertSame('accepted', $body['status']);
        self::assertArrayNotHasKey('result', $body);

        return $body;
    }

    private function auth(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'CONTENT_TYPE' => 'application/json'];
    }

    private function handleVoteCast(string $sessionId, string $voteId): void
    {
        self::getContainer()->get(VoteCastEventHandler::class)(new VoteCastEvent((int) $sessionId, (int) $voteId));
    }

    private function resultFor(string $sessionId): ?SessionResult
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $session = $entityManager->find(DecisionSession::class, (int) $sessionId);
        self::assertInstanceOf(DecisionSession::class, $session);

        $result = $entityManager->getRepository(SessionResult::class)->find($session);

        return $result instanceof SessionResult ? $result : null;
    }
}
