<?php

use App\Client\Resources\Meta\GetOrganizationRequest;
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

function websocketClusterApiResponse(array $overrides = []): array
{
    return array_merge([
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
    ], $overrides);
}

it('lists websocket clusters', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListWebSocketClustersRequest::class => MockResponse::make([
            'data' => [websocketClusterApiResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('websocket-cluster:list')
        ->assertSuccessful();
});

it('outputs empty JSON in non-interactive mode when no clusters found', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListWebSocketClustersRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    // Empty list now returns failure
    $this->artisan('websocket-cluster:list', ['--no-interaction' => true])
        ->assertFailed();
});

it('lists multiple websocket clusters', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListWebSocketClustersRequest::class => MockResponse::make([
            'data' => [
                websocketClusterApiResponse(),
                websocketClusterApiResponse([
                    'id' => 'ws-456',
                    'attributes' => [
                        'name' => 'second-cluster',
                        'type' => 'reverb',
                        'region' => 'eu-west-1',
                        'status' => 'creating',
                        'max_connections' => 500,
                        'connection_distribution_strategy' => 'evenly',
                        'hostname' => 'ws-456.cloud.laravel.com',
                        'created_at' => now()->toISOString(),
                    ],
                ]),
            ],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('websocket-cluster:list')
        ->assertSuccessful();
});
