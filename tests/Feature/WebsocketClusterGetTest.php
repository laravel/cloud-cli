<?php

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

function wsClusterGetFullResponse(array $overrides = []): array
{
    return [
        'data' => [
            'id' => $overrides['id'] ?? 'ws-123',
            'type' => 'websocketServers',
            'attributes' => array_merge([
                'name' => 'my-cluster',
                'type' => 'reverb',
                'region' => 'us-east-1',
                'status' => 'available',
                'max_connections' => 100,
                'connection_distribution_strategy' => 'evenly',
                'hostname' => 'ws-123.cloud.laravel.com',
                'created_at' => now()->toISOString(),
            ], $overrides['attributes'] ?? []),
            'relationships' => [
                'applications' => ['data' => []],
            ],
        ],
        'included' => [],
    ];
}

it('gets a websocket cluster by ID', function () {
    Prompt::fake();

    MockClient::global([
        GetWebSocketClusterRequest::class => MockResponse::make(wsClusterGetFullResponse(), 200),
    ]);

    $this->artisan('websocket-cluster:get', [
        'cluster' => 'ws-123',
    ])->assertSuccessful();
});

it('gets a websocket cluster by ID with --json flag', function () {
    MockClient::global([
        GetWebSocketClusterRequest::class => MockResponse::make(wsClusterGetFullResponse(), 200),
    ]);

    $this->artisan('websocket-cluster:get', [
        'cluster' => 'ws-123',
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"id"');
});

it('resolves websocket cluster by name', function () {
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
    ]);

    $this->artisan('websocket-cluster:get', [
        'cluster' => 'my-cluster',
    ])->assertSuccessful();
});

it('fails when websocket cluster not found by ID', function () {
    Prompt::fake();

    MockClient::global([
        GetWebSocketClusterRequest::class => MockResponse::make(['message' => 'Not found'], 404),
        ListWebSocketClustersRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('websocket-cluster:get', [
        'cluster' => 'ws-nonexistent',
        '--no-interaction' => true,
    ])->assertFailed();
});
