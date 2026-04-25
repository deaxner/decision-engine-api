<?php

namespace App\Tests\Unit\Request;

use App\UI\Http\Request\RequestInputMapper;
use PHPUnit\Framework\TestCase;

final class RequestInputMapperTest extends TestCase
{
    private RequestInputMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new RequestInputMapper();
    }

    public function testMapsSessionInputWithNormalizedFields(): void
    {
        $input = $this->mapper->createSession([
            'title' => '  Launch plan  ',
            'description' => '  Decide once  ',
            'voting_type' => 'RANKED_IRV',
            'category' => '  Product  ',
            'due_at' => '2026-04-28T12:00:00+00:00',
            'assignee_ids' => ['3', '3', '4'],
        ]);

        self::assertSame('Launch plan', $input->title);
        self::assertSame('Decide once', $input->description);
        self::assertSame('RANKED_IRV', $input->votingType);
        self::assertSame('Product', $input->category);
        self::assertSame(['3', '4'], $input->assigneeIds);
        self::assertSame('2026-04-28T12:00:00+00:00', $input->dueAt?->format(\DateTimeInterface::ATOM));
    }

    public function testRejectsMissingRequiredFields(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Missing required field: name.');

        $this->mapper->createWorkspace(['slug' => 'product']);
    }

    public function testRequiresMemberTargetForWorkspaceMembership(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Either email or user_id is required.');

        $this->mapper->addWorkspaceMember([]);
    }

    public function testRejectsNonArrayAssigneeIds(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('assignee_ids must be an array.');

        $this->mapper->createSession([
            'title' => 'Launch plan',
            'voting_type' => 'MAJORITY',
            'assignee_ids' => '3',
        ]);
    }
}
