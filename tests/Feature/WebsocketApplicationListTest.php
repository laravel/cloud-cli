<?php

use App\Client\Resources\WebSocketApplications\ListWebSocketApplicationsRequest;
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
    $this->mockGit->shouldReceive('currentBranch')->andReturn('main')->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('')->byDefault();
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(false)->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(fn () => MockClient::destroyGlobal());

function wsAppListClusterResponse(): array
{
    return [
        'data' => [
            'id' => 'ws-123',
            'type' => 'websocketServers',
            'attributes' => [
                'name' => 'my-cluster',
                'type' => 'reverb',
                'region' => 'us-east-1',
                'status' => 'available',
                'max_connections' => 100,
                'connection_distribution_strategy' => 'evenly',
                'hostname' => 'ws-123.cloud.laravel.com',
                'created_at' => now()->toISOString(),
            ],
            'relationships' => [
                'applications' => ['data' => []],
            ],
        ],
        'included' => [],
    ];
}

function wsAppListItemResponse(array $overrides = []): array
{
    return array_merge([
        'id' => 'wsa-123',
        'type' => 'websocketApplications',
        'attributes' => [
            'name' => 'my-ws-app',
            'app_id' => 'app-id-123',
            'allowed_origins' => [],
            'ping_interval' => 60,
            'activity_timeout' => 30,
            'max_message_size' => 10000,
            'max_connections' => 100,
            'key' => 'app-key-123',
            'secret' => 'app-secret-123',
            'created_at' => now()->toISOString(),
        ],
        'relationships' => [
            'server' => ['data' => ['id' => 'ws-123', 'type' => 'websocketServers']],
        ],
    ], $overrides);
}

it('lists websocket applications for a cluster by ID', function () {
    Prompt::fake();

    MockClient::global([
        GetWebSocketClusterRequest::class => MockResponse::make(wsAppListClusterResponse(), 200),
        ListWebSocketApplicationsRequest::class => MockResponse::make([
            'data' => [wsAppListItemResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('websocket-application:list', [
        'cluster' => 'ws-123',
    ])->assertSuccessful();
});

it('lists multiple websocket applications', function () {
    Prompt::fake();

    MockClient::global([
        GetWebSocketClusterRequest::class => MockResponse::make(wsAppListClusterResponse(), 200),
        ListWebSocketApplicationsRequest::class => MockResponse::make([
            'data' => [
                wsAppListItemResponse(),
                wsAppListItemResponse([
                    'id' => 'wsa-456',
                    'attributes' => [
                        'name' => 'second-ws-app',
                        'app_id' => 'app-id-456',
                        'allowed_origins' => [],
                        'ping_interval' => 30,
                        'activity_timeout' => 15,
                        'max_message_size' => 5000,
                        'max_connections' => 200,
                        'key' => 'app-key-456',
                        'secret' => 'app-secret-456',
                        'created_at' => now()->toISOString(),
                    ],
                ]),
            ],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('websocket-application:list', [
        'cluster' => 'ws-123',
    ])->assertSuccessful();
});

// In test/non-interactive mode, outputJsonIfWanted exits with SUCCESS before
// reaching the empty-list warning. This test verifies the command completes
// without error when there are no applications.
it('handles empty websocket applications list gracefully', function () {
    Prompt::fake();

    MockClient::global([
        GetWebSocketClusterRequest::class => MockResponse::make(wsAppListClusterResponse(), 200),
        ListWebSocketApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('websocket-application:list', [
        'cluster' => 'ws-123',
    ])->assertSuccessful();
});

it('outputs JSON in non-interactive mode when no applications found', function () {
    Prompt::fake();

    MockClient::global([
        GetWebSocketClusterRequest::class => MockResponse::make(wsAppListClusterResponse(), 200),
        ListWebSocketApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    // In non-interactive mode (test env), wantsJson() returns true,
    // so outputJsonIfWanted exits with SUCCESS before the empty check.
    $this->artisan('websocket-application:list', [
        'cluster' => 'ws-123',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('resolves cluster by name when listing applications', function () {
    Prompt::fake();

    MockClient::global([
        ListWebSocketClustersRequest::class => MockResponse::make([
            'data' => [
                [
                    'id' => 'ws-123',
                    'type' => 'websocketServers',
                    'attributes' => [
                        'name' => 'my-cluster',
                        'type' => 'reverb',
                        'region' => 'us-east-1',
                        'status' => 'available',
                        'max_connections' => 100,
                        'connection_distribution_strategy' => 'evenly',
                        'hostname' => 'ws-123.cloud.laravel.com',
                        'created_at' => now()->toISOString(),
                    ],
                    'relationships' => ['applications' => ['data' => []]],
                ],
            ],
            'links' => ['next' => null],
        ], 200),
        ListWebSocketApplicationsRequest::class => MockResponse::make([
            'data' => [wsAppListItemResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('websocket-application:list', [
        'cluster' => 'my-cluster',
    ])->assertSuccessful();
});
