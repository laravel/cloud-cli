<?php

use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
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

function databaseClusterResponse(array $overrides = []): array
{
    return array_merge([
        'id' => 'db-cluster-1',
        'type' => 'databaseClusters',
        'attributes' => [
            'name' => 'my-cluster',
            'type' => 'laravel_mysql_8',
            'status' => 'running',
            'region' => 'us-east-1',
            'config' => [],
            'connection' => [],
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ],
    ], $overrides);
}

it('lists database clusters successfully', function () {
    Prompt::fake();

    MockClient::global([
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [databaseClusterResponse()],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('database-cluster:list')
        ->assertSuccessful();
});

it('outputs empty JSON when no database clusters exist in non-interactive mode', function () {
    MockClient::global([
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    // Non-interactive mode outputs JSON (empty collection) and exits successfully
    $this->artisan('database-cluster:list', ['--no-interaction' => true])
        ->assertSuccessful();
});

it('outputs empty JSON with --json when no database clusters exist', function () {
    MockClient::global([
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('database-cluster:list', ['--json' => true])
        ->assertSuccessful();
});

it('lists database clusters with JSON output', function () {
    MockClient::global([
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [databaseClusterResponse()],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('database-cluster:list', ['--json' => true])
        ->assertSuccessful();
});

it('lists multiple database clusters', function () {
    Prompt::fake();

    MockClient::global([
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [
                databaseClusterResponse(),
                databaseClusterResponse(['id' => 'db-cluster-2', 'attributes' => [
                    'name' => 'second-cluster',
                    'type' => 'neon_serverless_postgres_17',
                    'status' => 'running',
                    'region' => 'us-east-2',
                    'config' => [],
                    'connection' => [],
                ]]),
            ],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('database-cluster:list')
        ->assertSuccessful();
});

it('lists database clusters with schemas included', function () {
    Prompt::fake();

    MockClient::global([
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [databaseClusterResponse()],
            'included' => [
                [
                    'id' => '1',
                    'type' => 'databaseSchemas',
                    'attributes' => [
                        'name' => 'my_database',
                        'created_at' => now()->toISOString(),
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('database-cluster:list')
        ->assertSuccessful();
});
