<?php

namespace App\Tests\Functional;

use App\Domain\Decision\Entity\Vote;
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

        $this->castMajorityVote($client, $owner['token'], $session['id'], $optionA['id']);
        $this->castMajorityVote($client, $owner['token'], $session['id'], $optionB['id']);

        $client->request('GET', '/sessions/'.$session['id'].'/results', server: $this->auth($owner['token']));
        self::assertResponseIsSuccessful();
        $result = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($optionB['id'], $result['winning_option_id']);
        self::assertSame(1, $result['result_data']['total_votes']);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
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

    private function castMajorityVote($client, string $token, string $sessionId, string $optionId): void
    {
        $client->request('POST', '/sessions/'.$sessionId.'/votes', server: $this->auth($token), content: json_encode([
            'version' => 1,
            'type' => 'MAJORITY',
            'data' => ['choice' => $optionId],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(202);
    }

    private function auth(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'CONTENT_TYPE' => 'application/json'];
    }
}
