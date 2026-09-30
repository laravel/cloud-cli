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

    if (isset($overrides['relationships'])) {
        $base['relationships'] = array_merge($base['relationships'] ?? [], $overrides['relationships']);
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

/**
 * The key pair the command's encrypted values can be decrypted with, so the suite never
 * needs a real organization key pair.
 */
function secretKeyPair(): string
{
    static $keyPair = null;

    return $keyPair ??= sodium_crypto_box_keypair();
}

function secretPublicKeyResponse(): array
{
    return [
        'data' => [
            'id' => 'keypair-1',
            'type' => 'organization-key-pairs',
            'attributes' => [
                'public_key' => base64_encode(sodium_crypto_box_publickey(secretKeyPair())),
            ],
        ],
    ];
}

function decryptSecretValue(string $value): string
{
    return sodium_crypto_box_seal_open(
        base64_decode($value, strict: true),
        secretKeyPair(),
    );
}

function secretResponse(array $overrides = []): array
{
    return [
        'id' => $overrides['id'] ?? 'secret-1',
        'type' => 'secrets',
        'attributes' => array_merge([
            'key' => 'STRIPE_KEY',
            'notes' => null,
            'created_at' => '2026-01-01T00:00:00.000000Z',
            'updated_at' => '2026-01-01T00:00:00.000000Z',
        ], $overrides['attributes'] ?? []),
    ];
}

function bucketKeyResponse(array $overrides = []): array
{
    return [
        'id' => $overrides['id'] ?? 'flsk-1',
        'type' => 'filesystemKeys',
        'attributes' => array_merge([
            'name' => 'my-key',
            'permission' => 'read_write',
            'access_key_id' => 'AKIAEXAMPLE123',
            'access_key_secret' => 'super-secret-value',
            'created_at' => '2026-01-01T00:00:00.000000Z',
        ], $overrides['attributes'] ?? []),
    ];
}

function bucketResponse(array $overrides = []): array
{
    return [
        'id' => $overrides['id'] ?? 'fls-1',
        'type' => 'filesystems',
        'attributes' => array_merge([
            'name' => 'my-bucket',
            'type' => 'cloudflare_r2',
            'status' => 'available',
            'visibility' => 'private',
            'jurisdiction' => 'default',
            'endpoint' => 'https://example.r2.cloudflarestorage.com',
            'url' => null,
            'allowed_origins' => null,
            'created_at' => '2026-01-01T00:00:00.000000Z',
        ], $overrides['attributes'] ?? []),
    ];
}

function databaseSchemaResponse(array $overrides = []): array
{
    return [
        'id' => $overrides['id'] ?? 'schema-1',
        'type' => 'databaseSchemas',
        'attributes' => array_merge([
            'name' => 'my_schema',
            'status' => 'available',
            'created_at' => '2024-01-15T12:00:00.000000Z',
        ], $overrides['attributes'] ?? []),
    ];
}

function websocketClusterResponse(array $overrides = []): array
{
    return [
        'id' => $overrides['id'] ?? 'wss-1',
        'type' => 'websocketServers',
        'attributes' => array_merge([
            'name' => 'my-reverb',
            'type' => 'reverb',
            'region' => 'us-east-1',
            'status' => 'available',
            'max_connections' => 500,
            'connection_distribution_strategy' => 'evenly',
            'hostname' => 'my-reverb.cloud.laravel.com',
            'created_at' => '2026-01-01T00:00:00.000000Z',
        ], $overrides['attributes'] ?? []),
    ];
}

function databaseSnapshotResponse(array $overrides = []): array
{
    return [
        'id' => $overrides['id'] ?? 'snap-1',
        'type' => 'databaseSnapshots',
        'attributes' => array_merge([
            'name' => 'nightly',
            'status' => 'available',
            'created_at' => '2026-01-01T00:00:00.000000Z',
        ], $overrides['attributes'] ?? []),
    ];
}

function databaseClusterResponse(array $overrides = []): array
{
    $schemas = $overrides['included'] ?? [databaseSchemaResponse()];

    return [
        'data' => [
            'id' => $overrides['id'] ?? 'db-123',
            'type' => 'databaseClusters',
            'attributes' => array_merge([
                'name' => 'my-cluster',
                'type' => 'laravel-mysql',
                'status' => 'available',
                'region' => 'us-east-1',
                'config' => [],
                'connection' => [],
            ], $overrides['attributes'] ?? []),
            'relationships' => [
                'databases' => [
                    'data' => array_map(
                        fn ($schema) => ['id' => $schema['id'], 'type' => $schema['type']],
                        $schemas,
                    ),
                ],
            ],
        ],
        'included' => $schemas,
    ];
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

/**
 * The shape GET /databases/types serves: a versionless type with the creatable
 * versions listed alongside it. The CLI must not expect a version in the type.
 */
function versionlessDatabaseTypesResponse(): array
{
    return [
        'data' => [
            [
                'type' => 'laravel_mysql',
                'label' => 'Laravel MySQL',
                'versions' => ['8.4'],
                'regions' => ['us-east-1'],
                'config_schema' => [
                    ['name' => 'size', 'type' => 'string', 'required' => true, 'example' => 'db-flex.m-1vcpu-512mb'],
                    ['name' => 'storage', 'type' => 'integer', 'required' => true, 'example' => '5'],
                    ['name' => 'retention_days', 'type' => 'integer', 'required' => true, 'example' => '1'],
                    ['name' => 'uses_scheduled_snapshots', 'type' => 'boolean', 'required' => true, 'example' => 'false'],
                    ['name' => 'is_public', 'type' => 'boolean', 'required' => true, 'example' => 'false'],
                ],
            ],
            [
                'type' => 'aws_rds_postgres',
                'label' => 'AWS RDS Postgres',
                'versions' => ['18'],
                'regions' => ['us-east-1'],
                'config_schema' => [],
            ],
            [
                'type' => 'neon_serverless_postgres',
                'label' => 'Laravel Serverless Postgres',
                'versions' => ['16', '17', '18'],
                'regions' => ['us-east-1'],
                'config_schema' => [
                    ['name' => 'cu_min', 'type' => 'number', 'required' => true, 'example' => '0.25'],
                    ['name' => 'cu_max', 'type' => 'number', 'required' => true, 'example' => '0.25'],
                    ['name' => 'suspend_seconds', 'type' => 'integer', 'required' => true, 'example' => '300'],
                    ['name' => 'retention_days', 'type' => 'integer', 'required' => true, 'example' => '0'],
                ],
            ],
        ],
    ];
}

function cacheResponse(array $overrides = []): array
{
    return [
        'id' => $overrides['id'] ?? 'cache-1',
        'type' => 'caches',
        'attributes' => array_merge([
            'name' => 'my-cache',
            'type' => 'laravel_valkey',
            'status' => 'available',
            'region' => 'us-east-1',
            'size' => '250mb',
            'auto_upgrade_enabled' => false,
            'is_public' => false,
            'created_at' => '2026-01-01T00:00:00.000000Z',
        ], $overrides['attributes'] ?? []),
    ];
}

function websocketApplicationResponse(array $overrides = []): array
{
    return [
        'id' => $overrides['id'] ?? 'wsa-1',
        'type' => 'websocketApplications',
        'attributes' => array_merge([
            'name' => 'my-ws-app',
            'app_id' => '123456',
            'allowed_origins' => [],
            'ping_interval' => 60,
            'activity_timeout' => 30,
            'max_message_size' => 10000,
            'max_connections' => 100,
            'key' => 'key',
            'secret' => 'secret',
            'created_at' => '2026-01-01T00:00:00.000000Z',
        ], $overrides['attributes'] ?? []),
    ];
}

function metricsResponse(array $data, array $overrides = []): array
{
    return array_replace_recursive([
        'data' => $data,
        'meta' => [
            'period' => '24h',
            'available_periods' => ['6h', '24h', '3d', '7d', '30d'],
        ],
    ], $overrides);
}

function databaseClusterMetricsResponse(array $overrides = []): array
{
    return metricsResponse([
        'cpu_usage' => [
            'data' => [
                ['x' => '2026-08-14T12:00:00.000000Z', 'y' => 0.004],
                ['x' => '2026-08-14T12:02:00.000000Z', 'y' => 0.008],
            ],
            'average' => 0.006,
            'max' => 0.008,
        ],
        'compute_hours' => ['data' => [], 'total' => 0],
        'memory_usage' => [
            'data' => [
                ['x' => '2026-08-14T12:00:00.000000Z', 'y' => 269451264],
                ['x' => '2026-08-14T12:02:00.000000Z', 'y' => 270655488],
            ],
            'current' => 270655488,
            'min' => 265416704,
            'max' => 275480576,
        ],
        'writes' => ['data' => [], 'total' => 0],
        'storage_usage' => [
            'data' => [
                ['x' => '2026-08-14T12:00:00.000000Z', 'y' => 6291456],
                ['x' => '2026-08-14T12:02:00.000000Z', 'y' => 6291456],
            ],
            'current' => 6291456,
        ],
        'replica_lag' => [],
    ], $overrides);
}

function cacheMetricsResponse(array $overrides = []): array
{
    return metricsResponse([
        'hits_and_misses' => [
            'labels' => ['Hits', 'Misses'],
            'average' => [40, 2],
            'data' => [
                ['x' => '2026-08-14T12:00:00.000000Z', 'y' => [30, 1]],
                ['x' => '2026-08-14T12:05:00.000000Z', 'y' => [50, 3]],
            ],
        ],
        'throughput' => [
            'labels' => ['Reads', 'Writes'],
            'average' => [12, 4],
            'data' => [
                ['x' => '2026-08-14T12:00:00.000000Z', 'y' => [10, 3]],
                ['x' => '2026-08-14T12:05:00.000000Z', 'y' => [14, 5]],
            ],
        ],
        'size' => [
            'data' => [
                ['x' => '2026-08-14T12:00:00.000000Z', 'y' => 1048576],
                ['x' => '2026-08-14T12:05:00.000000Z', 'y' => 2097152],
            ],
            'total' => 2097152,
        ],
        'bandwidth_usage' => ['data' => [], 'total' => 0],
    ], $overrides);
}

function environmentMetricsResponse(array $overrides = []): array
{
    $labelled = fn (array $labels, array $first, array $second) => [
        'labels' => $labels,
        'average' => array_map(fn ($a, $b) => ($a + $b) / 2, $first, $second),
        'min' => min([...$first, ...$second]),
        'max' => max([...$first, ...$second]),
        'data' => [
            ['x' => '2026-08-14T12:00:00.000000Z', 'y' => $first],
            ['x' => '2026-08-14T12:05:00.000000Z', 'y' => $second],
        ],
    ];

    return metricsResponse([
        'cpu_usage' => $labelled(['App', 'Worker'], [20, 5], [30, 15]),
        'memory_usage' => $labelled(['App', 'Worker'], [40, 10], [50, 20]),
        'http_response_count' => [...$labelled(['2xx', '5xx'], [100, 0], [120, 2]), 'total' => 222],
        'replica_count' => $labelled(['App'], [1], [2]),
        'web_workers_count' => ['error' => true],
    ], $overrides);
}

function websocketMetricsResponse(array $overrides = []): array
{
    return metricsResponse([
        'connection_count' => [
            'data' => [
                ['x' => '2026-08-14T12:00:00.000000Z', 'y' => 10],
                ['x' => '2026-08-14T12:05:00.000000Z', 'y' => 30],
            ],
            'current' => 30,
            'max' => 30,
            'average' => 20,
        ],
        'message_rate' => [
            'data' => [
                ['x' => '2026-08-14T12:00:00.000000Z', 'y' => 1.5],
                ['x' => '2026-08-14T12:05:00.000000Z', 'y' => 2.5],
            ],
            'average' => 2,
            'max' => 2.5,
        ],
    ], $overrides);
}
