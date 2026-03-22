<?php

use App\Client\Resources\WebSocketApplications\GetWebSocketApplicationRequest;
use App\Client\Resources\WebSocketApplications\ListWebSocketApplicationsRequest;
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

function wsAppGetFullResponse(array $overrides = []): array
{
    return [
        'data' => [
            'id' => $overrides['id'] ?? 'wsa-123',
            'type' => 'websocketApplications',
            'attributes' => array_merge([
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
            ], $overrides['attributes'] ?? []),
            'relationships' => [
                'server' => ['data' => ['id' => 'ws-123', 'type' => 'websocketServers']],
            ],
        ],
        'included' => [],
    ];
}

it('gets a websocket application by ID', function () {
    Prompt::fake();

    MockClient::global([
        GetWebSocketApplicationRequest::class => MockResponse::make(wsAppGetFullResponse(), 200),
    ]);

    $this->artisan('websocket-application:get', [
        'application' => 'wsa-123',
    ])->assertSuccessful();
});

it('gets a websocket application by ID with --json flag', function () {
    MockClient::global([
        GetWebSocketApplicationRequest::class => MockResponse::make(wsAppGetFullResponse(), 200),
    ]);

    $this->artisan('websocket-application:get', [
        'application' => 'wsa-123',
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"id"');
});

it('resolves websocket application by name via cluster lookup', function () {
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
            'data' => [
                [
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
                ],
            ],
            'links' => ['next' => null],
        ], 200),
        GetWebSocketApplicationRequest::class => MockResponse::make(wsAppGetFullResponse(), 200),
    ]);

    $this->artisan('websocket-application:get', [
        'application' => 'my-ws-app',
    ])->assertSuccessful();
});

it('fails when websocket application not found by ID', function () {
    Prompt::fake();

    MockClient::global([
        GetWebSocketApplicationRequest::class => MockResponse::make(['message' => 'Not found'], 404),
        ListWebSocketClustersRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
        ListWebSocketApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('websocket-application:get', [
        'application' => 'wsa-nonexistent',
        '--no-interaction' => true,
    ])->assertFailed();
});
