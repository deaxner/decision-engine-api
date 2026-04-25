<?php

namespace App\Tests\Unit\Output;

use App\Application\Decision\Output\ActivityEventOutput;
use App\Application\Decision\Output\DecisionOptionOutput;
use App\Application\Decision\Output\SessionAssigneeOutput;
use App\Application\Decision\Output\SessionOutput;
use App\Application\Decision\Output\SessionResultOutput;
use App\Application\Decision\Output\WorkspaceDashboardOutput;
use App\Application\Decision\Output\WorkspaceInsightOutput;
use App\Application\Decision\Output\WorkspaceMetricsOutput;
use App\Application\Decision\Output\WorkspaceOutput;
use PHPUnit\Framework\TestCase;

final class ResponseOutputSerializationTest extends TestCase
{
    /**
     * @return array<string,mixed>
     */
    private function normalize(\JsonSerializable $output): array
    {
        return json_decode(json_encode($output, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testSessionOutputSerializesToExistingApiShape(): void
    {
        $output = new SessionOutput(
            id: '20',
            title: 'Choose launch plan',
            description: 'Pick one',
            category: 'Product',
            status: 'OPEN',
            votingType: 'RANKED_IRV',
            dueAt: '2026-04-28T12:00:00+00:00',
            startsAt: '2026-04-24T12:00:00+00:00',
            endsAt: null,
            assignees: [new SessionAssigneeOutput('3', 'Member User', 'member@example.test')],
            options: [new DecisionOptionOutput('10', 'Option A', 1)],
        );

        self::assertSame([
            'id' => '20',
            'title' => 'Choose launch plan',
            'description' => 'Pick one',
            'category' => 'Product',
            'status' => 'OPEN',
            'voting_type' => 'RANKED_IRV',
            'due_at' => '2026-04-28T12:00:00+00:00',
            'starts_at' => '2026-04-24T12:00:00+00:00',
            'ends_at' => null,
            'assignees' => [
                ['id' => '3', 'display_name' => 'Member User', 'email' => 'member@example.test'],
            ],
            'options' => [
                ['id' => '10', 'title' => 'Option A', 'position' => 1],
            ],
        ], $this->normalize($output));
    }

    public function testWorkspaceDashboardOutputSerializesToExistingApiShape(): void
    {
        $output = new WorkspaceDashboardOutput(
            workspace: new WorkspaceOutput('10', 'Product', 'product', 4, 50, ['total' => 2, 'draft' => 1, 'open' => 1, 'closed' => 0], 'OWNER'),
            metrics: new WorkspaceMetricsOutput(4.2, 50, 1, 1, 0),
            activity: [
                new ActivityEventOutput(
                    id: '900',
                    type: 'vote_cast',
                    summary: 'Owner cast a vote.',
                    actor: ['id' => '1', 'display_name' => 'Owner'],
                    workspaceId: '10',
                    sessionId: '20',
                    sessionTitle: 'Choose launch plan',
                    createdAt: '2026-04-25T12:00:00+00:00',
                    metadata: [],
                ),
            ],
            insights: [
                new WorkspaceInsightOutput('no-active-sessions', 'activity', 'info', 'No active voting sessions', 'Open a draft.', null),
            ],
        );

        self::assertSame([
            'workspace' => [
                'id' => '10',
                'name' => 'Product',
                'slug' => 'product',
                'member_count' => 4,
                'participation_rate' => 50,
                'session_counts' => ['total' => 2, 'draft' => 1, 'open' => 1, 'closed' => 0],
                'role' => 'OWNER',
            ],
            'metrics' => [
                'decision_speed_days' => 4.2,
                'engagement_rate' => 50,
                'active_session_count' => 1,
                'draft_session_count' => 1,
                'closed_session_count' => 0,
            ],
            'activity' => [[
                'id' => '900',
                'type' => 'vote_cast',
                'summary' => 'Owner cast a vote.',
                'actor' => ['id' => '1', 'display_name' => 'Owner'],
                'workspace_id' => '10',
                'session_id' => '20',
                'session_title' => 'Choose launch plan',
                'created_at' => '2026-04-25T12:00:00+00:00',
                'metadata' => [],
            ]],
            'insights' => [[
                'id' => 'no-active-sessions',
                'kind' => 'activity',
                'severity' => 'info',
                'title' => 'No active voting sessions',
                'body' => 'Open a draft.',
                'session_id' => null,
            ]],
        ], $this->normalize($output));
    }

    public function testSessionResultOutputSerializesToExistingApiShape(): void
    {
        $output = new SessionResultOutput(
            sessionId: '20',
            version: 2,
            winningOptionId: '10',
            resultData: ['winner' => '10', 'rounds' => [], 'total_votes' => 2, 'computed_at' => '2026-04-25T12:00:00+00:00'],
            calculatedAt: '2026-04-25T12:00:00+00:00',
        );

        self::assertSame([
            'session_id' => '20',
            'version' => 2,
            'winning_option_id' => '10',
            'result_data' => ['winner' => '10', 'rounds' => [], 'total_votes' => 2, 'computed_at' => '2026-04-25T12:00:00+00:00'],
            'calculated_at' => '2026-04-25T12:00:00+00:00',
        ], $this->normalize($output));
    }
}
