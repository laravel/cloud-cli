<?php

use App\Client\Resources\WebSocketApplications\GetWebSocketApplicationRequest;
use App\Client\Resources\WebSocketApplications\UpdateWebSocketApplicationRequest;
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

function wsAppUpdateGetResponse(array $overrides = []): array
{
    return [
        'data' => [
            'id' => 'wsa-123',
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

it('updates a websocket application with --name and --force', function () {
    Prompt::fake();

    $getCallCount = 0;
    MockClient::global([
        GetWebSocketApplicationRequest::class => function () use (&$getCallCount) {
            $getCallCount++;

            if ($getCallCount === 1) {
                return MockResponse::make(wsAppUpdateGetResponse(), 200);
            }

            return MockResponse::make(wsAppUpdateGetResponse(['attributes' => ['name' => 'updated-app']]), 200);
        },
        UpdateWebSocketApplicationRequest::class => MockResponse::make(
            wsAppUpdateGetResponse(['attributes' => ['name' => 'updated-app']]),
            200,
        ),
    ]);

    $this->artisan('websocket-application:update', [
        'application' => 'wsa-123',
        '--name' => 'updated-app',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fails when no fields are provided in non-interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        GetWebSocketApplicationRequest::class => MockResponse::make(wsAppUpdateGetResponse(), 200),
    ]);

    $this->artisan('websocket-application:update', [
        'application' => 'wsa-123',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('updates websocket application name with --json output', function () {
    $getCallCount = 0;
    MockClient::global([
        GetWebSocketApplicationRequest::class => function () use (&$getCallCount) {
            $getCallCount++;

            if ($getCallCount === 1) {
                return MockResponse::make(wsAppUpdateGetResponse(), 200);
            }

            return MockResponse::make(wsAppUpdateGetResponse(['attributes' => ['name' => 'renamed-app']]), 200);
        },
        UpdateWebSocketApplicationRequest::class => MockResponse::make(
            wsAppUpdateGetResponse(['attributes' => ['name' => 'renamed-app']]),
            200,
        ),
    ]);

    $this->artisan('websocket-application:update', [
        'application' => 'wsa-123',
        '--name' => 'renamed-app',
        '--force' => true,
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"id"');
});
