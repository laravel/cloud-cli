<?php

use App\Client\Resources\WebSocketClusters\GetWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\UpdateWebSocketClusterRequest;
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

function wsClusterUpdateGetResponse(array $overrides = []): array
{
    return [
        'data' => [
            'id' => 'ws-123',
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

it('updates a websocket cluster with --name and --force', function () {
    Prompt::fake();

    $getCallCount = 0;
    MockClient::global([
        GetWebSocketClusterRequest::class => function () use (&$getCallCount) {
            $getCallCount++;
            if ($getCallCount === 1) {
                return MockResponse::make(wsClusterUpdateGetResponse(), 200);
            }

            return MockResponse::make(wsClusterUpdateGetResponse(['attributes' => ['name' => 'updated-cluster']]), 200);
        },
        UpdateWebSocketClusterRequest::class => MockResponse::make(
            wsClusterUpdateGetResponse(['attributes' => ['name' => 'updated-cluster']]),
            200
        ),
    ]);

    $this->artisan('websocket-cluster:update', [
        'cluster' => 'ws-123',
        '--name' => 'updated-cluster',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fails when no fields are provided in non-interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        GetWebSocketClusterRequest::class => MockResponse::make(wsClusterUpdateGetResponse(), 200),
    ]);

    $this->artisan('websocket-cluster:update', [
        'cluster' => 'ws-123',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('updates websocket cluster with --json output', function () {
    $getCallCount = 0;
    MockClient::global([
        GetWebSocketClusterRequest::class => function () use (&$getCallCount) {
            $getCallCount++;
            if ($getCallCount === 1) {
                return MockResponse::make(wsClusterUpdateGetResponse(), 200);
            }

            return MockResponse::make(wsClusterUpdateGetResponse(['attributes' => ['name' => 'renamed-cluster']]), 200);
        },
        UpdateWebSocketClusterRequest::class => MockResponse::make(
            wsClusterUpdateGetResponse(['attributes' => ['name' => 'renamed-cluster']]),
            200
        ),
    ]);

    $this->artisan('websocket-cluster:update', [
        'cluster' => 'ws-123',
        '--name' => 'renamed-cluster',
        '--force' => true,
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"id"');
});
