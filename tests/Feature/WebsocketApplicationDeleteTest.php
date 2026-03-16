<?php

use App\Client\Resources\WebSocketApplications\DeleteWebSocketApplicationRequest;
use App\Client\Resources\WebSocketApplications\GetWebSocketApplicationRequest;
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

function wsAppGetResponse(): array
{
    return [
        'data' => [
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
        'included' => [],
    ];
}

it('deletes a websocket application by ID with force flag', function () {
    Prompt::fake();

    MockClient::global([
        GetWebSocketApplicationRequest::class => MockResponse::make(wsAppGetResponse(), 200),
        DeleteWebSocketApplicationRequest::class => MockResponse::make([], 204),
    ]);

    $this->artisan('websocket-application:delete', [
        'application' => 'wsa-123',
        '--force' => true,
    ])->assertSuccessful();
});

// confirm(default: false) returns false when Prompt::fake() is used,
// causing the command to cancel and return FAILURE
it('cancels websocket application deletion when confirm defaults to false', function () {
    Prompt::fake();

    MockClient::global([
        GetWebSocketApplicationRequest::class => MockResponse::make(wsAppGetResponse(), 200),
    ]);

    $this->artisan('websocket-application:delete', [
        'application' => 'wsa-123',
    ])->assertFailed();
});
