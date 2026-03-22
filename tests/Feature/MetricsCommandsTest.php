<?php

use App\Client\Resources\Caches\GetCacheMetricsRequest;
use App\Client\Resources\Caches\ListCachesRequest;
use App\Client\Resources\DatabaseClusters\GetDatabaseClusterMetricsRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\Environments\GetEnvironmentMetricsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\WebSocketApplications\GetWebSocketApplicationMetricsRequest;
use App\Client\Resources\WebSocketApplications\GetWebSocketApplicationRequest;
use App\Client\Resources\WebSocketClusters\GetWebSocketClusterMetricsRequest;
use App\Client\Resources\WebSocketClusters\GetWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\ListWebSocketClustersRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Sleep::fake();

    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

it('fetches cache metrics successfully', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListCachesRequest::class => MockResponse::make([
            'data' => [
                [
                    'id' => 'cache-1',
                    'type' => 'caches',
                    'attributes' => [
                        'name' => 'My Cache',
                        'type' => 'redis',
                        'status' => 'running',
                        'region' => 'us-east-1',
                        'size' => '1GB',
                        'auto_upgrade_enabled' => true,
                        'is_public' => false,
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),
        GetCacheMetricsRequest::class => MockResponse::make([
            'data' => ['cpu' => '10%', 'memory' => '256MB'],
        ], 200),
    ]);

    $this->artisan('cache:metrics', [
        'cache' => 'My Cache',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fetches database cluster metrics successfully', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [
                [
                    'id' => 'db-1',
                    'type' => 'database_clusters',
                    'attributes' => [
                        'name' => 'My DB',
                        'type' => 'mysql',
                        'status' => 'running',
                        'region' => 'us-east-1',
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),
        GetDatabaseClusterMetricsRequest::class => MockResponse::make([
            'data' => ['cpu' => '15%', 'memory' => '512MB'],
        ], 200),
    ]);

    $this->artisan('database-cluster:metrics', [
        'cluster' => 'My DB',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fetches environment metrics successfully', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => createEnvironmentResponse(),
        ], 200),
        GetEnvironmentMetricsRequest::class => MockResponse::make([
            'data' => ['cpu' => '20%', 'memory' => '1GB'],
        ], 200),
    ]);

    $this->artisan('environment:metrics', [
        'environment' => 'env-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fetches websocket application metrics successfully', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetWebSocketApplicationRequest::class => MockResponse::make([
            'data' => [
                'id' => 'wsa-app-1',
                'type' => 'websocket_applications',
                'attributes' => [
                    'name' => 'My WS App',
                    'app_id' => 'app-123',
                    'key' => 'key-123',
                    'secret' => 'secret-123',
                    'allowed_origins' => [],
                    'max_connections' => 1000,
                    'ping_interval' => 30,
                    'activity_timeout' => 120,
                    'max_message_size' => 65536,
                ],
                'relationships' => [],
            ],
        ], 200),
        GetWebSocketApplicationMetricsRequest::class => MockResponse::make([
            'data' => ['connections' => 42, 'messages_per_second' => 100],
        ], 200),
    ]);

    $this->artisan('websocket-application:metrics', [
        'application' => 'wsa-app-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fetches websocket cluster metrics successfully', function () {
    Prompt::fake();

    $clusterData = [
        'id' => 'ws-cluster-1',
        'type' => 'websocket_clusters',
        'attributes' => [
            'name' => 'My WS Cluster',
            'region' => 'us-east-1',
            'status' => 'available',
            'type' => 'reverb',
            'hostname' => 'ws.example.com',
            'max_connections' => 10000,
            'connection_distribution_strategy' => 'evenly',
        ],
        'relationships' => [],
    ];

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetWebSocketClusterRequest::class => MockResponse::make(['data' => $clusterData], 200),
        ListWebSocketClustersRequest::class => MockResponse::make([
            'data' => [$clusterData],
            'links' => ['next' => null],
        ], 200),
        GetWebSocketClusterMetricsRequest::class => MockResponse::make([
            'data' => ['connections' => 100, 'bandwidth' => '5MB/s'],
        ], 200),
    ]);

    $this->artisan('websocket-cluster:metrics', [
        'cluster' => 'ws-cluster-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});
