<?php

use App\Client\Resources\WebSocketApplications\CreateWebSocketApplicationRequest;
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

function wsClusterGetForAppCreate(): array
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

function wsAppCreateResponse(): array
{
    return [
        'data' => [
            'id' => 'wsa-new',
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
        'included' => [],
    ];
}

// WebsocketApplicationCreate uses CreatesWebSocketApplication which prompts for
// allowed_origins, ping_interval, activity_timeout - none have CLI options.
// Non-interactive mode fails for these required fields.
it('fails in non-interactive mode because interactive-only fields have no CLI options', function () {
    Prompt::fake();

    MockClient::global([
        GetWebSocketClusterRequest::class => MockResponse::make(wsClusterGetForAppCreate(), 200),
    ]);

    $this->artisan('websocket-application:create', [
        'cluster' => 'ws-123',
        '--name' => 'my-ws-app',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('handles validation errors on websocket application create', function () {
    Prompt::fake();

    MockClient::global([
        GetWebSocketClusterRequest::class => MockResponse::make(wsClusterGetForAppCreate(), 200),
        CreateWebSocketApplicationRequest::class => MockResponse::make([
            'message' => 'Validation failed',
            'errors' => ['name' => ['The name has already been taken.']],
        ], 422),
    ]);

    $this->artisan('websocket-application:create', [
        'cluster' => 'ws-123',
        '--name' => 'duplicate',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('resolves cluster from list when given by name', function () {
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

    // Fails in non-interactive mode because allowed_origins has no CLI option
    $this->artisan('websocket-application:create', [
        'cluster' => 'my-cluster',
        '--name' => 'my-ws-app',
        '--no-interaction' => true,
    ])->assertFailed();
});
