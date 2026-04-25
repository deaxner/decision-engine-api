<?php

namespace App\Application\DemoData;

use App\Domain\Decision\Entity\DecisionSession;

final class DemoDataBlueprints
{
    public const DEFAULT_PASSWORD = 'Decision123!';
    public const SESSION_CATEGORIES = ['Governance', 'Infrastructure', 'Security', 'Product'];

    /** @var array<int, array{name: string, slug: string, owner: int, members: int[], sessions: array<int, array<string, mixed>>}> */
    public const WORKSPACE_BLUEPRINTS = [
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
    public const USER_BLUEPRINTS = [
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
}
