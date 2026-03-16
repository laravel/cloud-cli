<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
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

function dbClusterGetResponse(array $overrides = []): array
{
    return [
        'data' => array_merge([
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
        ], $overrides),
        'included' => [],
    ];
}

it('gets database cluster details by ID', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbClusterGetResponse(), 200),
    ]);

    $this->artisan('database-cluster:get', [
        'cluster' => 'db-cluster-1',
    ])->assertSuccessful();
});

it('gets database cluster details with JSON output', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbClusterGetResponse(), 200),
    ]);

    $this->artisan('database-cluster:get', [
        'cluster' => 'db-cluster-1',
        '--json' => true,
    ])->assertSuccessful();
});

it('resolves database cluster by name', function () {
    Prompt::fake();

    MockClient::global([
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [
                [
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
                ],
            ],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('database-cluster:get', [
        'cluster' => 'my-cluster',
    ])->assertSuccessful();
});

it('auto-selects sole cluster when no argument given', function () {
    Prompt::fake();

    MockClient::global([
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [
                [
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
                ],
            ],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('database-cluster:get', [
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('gets database cluster with schemas included', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make([
            'data' => [
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
            ],
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
        ], 200),
    ]);

    $this->artisan('database-cluster:get', [
        'cluster' => 'db-cluster-1',
    ])->assertSuccessful();
});

it('fails when no clusters found and no argument given', function () {
    MockClient::global([
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('database-cluster:get', [
        '--no-interaction' => true,
    ])->assertFailed();
});
