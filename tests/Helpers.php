<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

function createApplicationResponse(array $overrides = []): array
{
    $base = [
        'id' => 'app-123',
        'type' => 'applications',
        'attributes' => [
            'name' => 'My App',
            'slug' => 'my-app',
            'region' => 'us-east-1',
            'repository' => [
                'full_name' => 'user/my-app',
                'default_branch' => 'main',
            ],
        ],
        'relationships' => [
            'organization' => ['data' => ['id' => 'org-1', 'type' => 'organizations']],
            'environments' => ['data' => [['id' => 'env-1', 'type' => 'environments']]],
            'defaultEnvironment' => ['data' => ['id' => 'env-1', 'type' => 'environments']],
        ],
    ];

    if (isset($overrides['id'])) {
        $base['id'] = $overrides['id'];
    }

    if (isset($overrides['attributes'])) {
        $base['attributes'] = array_merge($base['attributes'], $overrides['attributes']);
    }

    if (isset($overrides['relationships'])) {
        $base['relationships'] = array_merge($base['relationships'], $overrides['relationships']);
    }

    return $base;
}

function createEnvironmentResponse(array $overrides = []): array
{
    $base = [
        'id' => 'env-1',
        'type' => 'environments',
        'attributes' => [
            'name' => 'production',
            'slug' => 'production',
            'vanity_domain' => 'my-app.cloud.laravel.com',
            'status' => 'running',
            'php_major_version' => '8.3',
        ],
    ];

    if (isset($overrides['id'])) {
        $base['id'] = $overrides['id'];
    }

    if (isset($overrides['attributes'])) {
        $base['attributes'] = array_merge($base['attributes'], $overrides['attributes']);
    }

    return $base;
}

function organizationResponse(): array
{
    return [
        'data' => [
            'id' => 'org-1',
            'type' => 'organizations',
            'attributes' => ['name' => 'My Org', 'slug' => 'my-org'],
        ],
    ];
}

function regionsResponse(): array
{
    return [
        'data' => [
            ['region' => 'us-east-1', 'label' => 'US East', 'flag' => 'us'],
        ],
    ];
}

function setupApplicationListMocks(?array $applications = null, int $status = 200): void
{
    $applications = $applications ?? [createApplicationResponse()];

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => $applications,
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
                ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
            ],
            'links' => ['next' => null],
        ], $status),
    ]);
}

function usageResponse(array $overrides = []): array
{
    $base = [
        'data' => [
            'summary' => [
                'current_spend_cents' => 12345,
                'bandwidth' => [
                    'cost_cents' => 100,
                    'usage_percentage' => 42,
                    'allowance_bytes' => 107374182400,
                ],
                'credits' => [
                    'used_cents' => 250,
                    'total_cents' => 1000,
                ],
                'alert' => [
                    'threshold_cents' => 50000,
                    'remaining_percentage' => 75,
                ],
            ],
            'resources' => [
                'total_cost_cents' => 8000,
                'databases' => [
                    [
                        'name' => 'primary',
                        'identifier' => 'db-1',
                        'type' => 'serverless-postgres',
                        'storage_gb' => 12.5,
                        'storage_cents' => 1500,
                        'compute_units' => 3.2,
                        'compute_unit_label' => 'CU',
                        'compute_cents' => 2000,
                        'backups_gb' => 4,
                        'backups_cents' => 500,
                        'total_cents' => 4000,
                    ],
                ],
                'caches' => [
                    [
                        'name' => 'sessions',
                        'identifier' => 'cache-1',
                        'type' => 'valkey',
                        'storage' => '256 MB',
                        'compute_hours' => 720,
                        'compute_cents' => 1500,
                        'total_cents' => 1500,
                    ],
                ],
                'buckets' => [
                    [
                        'name' => 'media',
                        'identifier' => 'bucket-1',
                        'class_a_requests_count' => 1000,
                        'class_a_requests_cents' => 50,
                        'class_b_requests_count' => 5000,
                        'class_b_requests_cents' => 25,
                        'storage_gb' => 8,
                        'storage_cents' => 200,
                        'total_cents' => 275,
                    ],
                ],
                'websockets' => [
                    [
                        'name' => 'realtime',
                        'identifier' => 'ws-1',
                        'max_connections' => 250,
                        'usage_time_hours' => 720,
                        'usage_time_cents' => 1000,
                        'total_cents' => 1000,
                    ],
                ],
            ],
            'addons' => [
                'total_cost_cents' => 1500,
                'items' => [
                    ['name' => 'Custom domain SSL', 'total_cents' => 1500],
                ],
            ],
            'application_totals' => [
                'total_cost_cents' => 5000,
                'application_count' => 1,
                'applications' => [
                    ['identifier' => 'app-123', 'total_cost_cents' => 5000],
                ],
            ],
            'environment_usage' => [
                'total_cost_cents' => 5000,
                'items' => [
                    [
                        'identifier' => 'production',
                        'type' => 'app',
                        'compute_profile' => 'standard-1',
                        'compute_description' => '1 vCPU / 2GB',
                        'cpu_hours' => 720,
                        'total_cents' => 5000,
                    ],
                ],
            ],
        ],
        'meta' => [
            'currency' => 'USD',
            'period' => 0,
            'available_periods' => [
                ['from' => '2026-04-01T00:00:00Z', 'to' => '2026-04-30T23:59:59Z'],
                ['from' => '2026-03-01T00:00:00Z', 'to' => '2026-03-31T23:59:59Z'],
            ],
            'last_updated_at' => '2026-04-29T10:00:00Z',
        ],
    ];

    return array_replace_recursive($base, $overrides);
}
