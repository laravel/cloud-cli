<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
use App\Client\Resources\WebSocketClusters\CreateWebSocketClusterRequest;
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

function wsClusterCreateResponse(): array
{
    return [
        'data' => [
            'id' => 'ws-new',
            'type' => 'websocketServers',
            'attributes' => [
                'name' => 'my-cluster',
                'type' => 'reverb',
                'region' => 'us-east-1',
                'status' => 'creating',
                'max_connections' => 100,
                'connection_distribution_strategy' => 'evenly',
                'hostname' => 'ws-new.cloud.laravel.com',
                'created_at' => now()->toISOString(),
            ],
            'relationships' => [
                'applications' => ['data' => []],
            ],
        ],
        'included' => [],
    ];
}

// WebsocketClusterCreate uses CreatesWebSocketCluster trait which prompts for
// name, region (via select), and max_connections (via select).
// The max_connections field has no CLI option, so non-interactive mode fails.
// This test verifies the non-interactive failure behavior.
it('fails in non-interactive mode because max_connections has no CLI option', function () {
    Prompt::fake();

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [createApplicationResponse()],
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org']],
                createEnvironmentResponse(),
            ],
            'links' => ['next' => null],
        ], 200),
        ListRegionsRequest::class => MockResponse::make([
            'data' => [
                ['region' => 'us-east-1', 'label' => 'US East', 'flag' => 'us'],
            ],
        ], 200),
    ]);

    $this->artisan('websocket-cluster:create', [
        '--name' => 'my-cluster',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('handles validation errors on websocket cluster create', function () {
    Prompt::fake();

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [createApplicationResponse()],
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org']],
                createEnvironmentResponse(),
            ],
            'links' => ['next' => null],
        ], 200),
        ListRegionsRequest::class => MockResponse::make([
            'data' => [
                ['region' => 'us-east-1', 'label' => 'US East', 'flag' => 'us'],
            ],
        ], 200),
        CreateWebSocketClusterRequest::class => MockResponse::make([
            'message' => 'Validation failed',
            'errors' => ['name' => ['The name has already been taken.']],
        ], 422),
    ]);

    $this->artisan('websocket-cluster:create', [
        '--name' => 'duplicate',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertFailed();
});
