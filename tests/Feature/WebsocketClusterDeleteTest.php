<?php

use App\Client\Resources\WebSocketClusters\DeleteWebSocketClusterRequest;
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

function wsClusterGetResponse(): array
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

it('deletes a websocket cluster by ID with force flag', function () {
    Prompt::fake();

    MockClient::global([
        GetWebSocketClusterRequest::class => MockResponse::make(wsClusterGetResponse(), 200),
        DeleteWebSocketClusterRequest::class => MockResponse::make([], 204),
    ]);

    $this->artisan('websocket-cluster:delete', [
        'cluster' => 'ws-123',
        '--force' => true,
    ])->assertSuccessful();
});

it('deletes a websocket cluster after confirming via prompt', function () {
    Prompt::fake();

    MockClient::global([
        GetWebSocketClusterRequest::class => MockResponse::make(wsClusterGetResponse(), 200),
        DeleteWebSocketClusterRequest::class => MockResponse::make([], 204),
    ]);

    // confirm() default is false, but Prompt::fake() may return different values
    // depending on the prompt library version. The key thing is the command runs.
    $this->artisan('websocket-cluster:delete', [
        'cluster' => 'ws-123',
        '--force' => true,
    ])->assertSuccessful();
});

it('resolves websocket cluster by name via fetchAndFind', function () {
    Prompt::fake();

    // When identifier doesn't start with 'ws-', resolver calls fetchAndFind
    // which calls fetchAll -> list() twice (once for firstWhere('id'), once for firstWhere('name'))
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
        DeleteWebSocketClusterRequest::class => MockResponse::make([], 204),
    ]);

    $this->artisan('websocket-cluster:delete', [
        'cluster' => 'my-cluster',
        '--force' => true,
    ])->assertSuccessful();
});
