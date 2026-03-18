<?php

use App\Client\Resources\DatabaseClusters\DeleteDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\Databases\DeleteDatabaseRequest;
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

function dbClusterDeleteGetResponse(array $schemasIncluded = []): array
{
    return [
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
        'included' => $schemasIncluded,
    ];
}

it('deletes a database cluster with force flag and no schemas', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbClusterDeleteGetResponse(), 200),
        DeleteDatabaseClusterRequest::class => MockResponse::make([], 200),
    ]);

    $this->artisan('database-cluster:delete', [
        'database' => 'db-cluster-1',
        '--force' => true,
    ])->assertSuccessful();
});

it('deletes a database cluster with schemas using force flag', function () {
    Prompt::fake();

    $schemas = [
        [
            'id' => '1',
            'type' => 'databaseSchemas',
            'attributes' => ['name' => 'my_database', 'created_at' => now()->toISOString()],
        ],
    ];

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbClusterDeleteGetResponse($schemas), 200),
        DeleteDatabaseRequest::class => MockResponse::make([], 200),
        DeleteDatabaseClusterRequest::class => MockResponse::make([], 200),
    ]);

    $this->artisan('database-cluster:delete', [
        'database' => 'db-cluster-1',
        '--force' => true,
    ])->assertSuccessful();
});

it('resolves database cluster by name when not an ID', function () {
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
                    ],
                ],
            ],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        GetDatabaseClusterRequest::class => MockResponse::make(dbClusterDeleteGetResponse(), 200),
        DeleteDatabaseClusterRequest::class => MockResponse::make([], 200),
    ]);

    $this->artisan('database-cluster:delete', [
        'database' => 'my-cluster',
        '--force' => true,
    ])->assertSuccessful();
});

it('deletes with JSON output', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbClusterDeleteGetResponse(), 200),
        DeleteDatabaseClusterRequest::class => MockResponse::make([], 200),
    ]);

    $this->artisan('database-cluster:delete', [
        'database' => 'db-cluster-1',
        '--force' => true,
        '--json' => true,
    ])->assertSuccessful();
});

it('fails when no database clusters found in non-interactive mode', function () {
    MockClient::global([
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('database-cluster:delete', [
        'database' => 'nonexistent',
        '--no-interaction' => true,
    ])->assertFailed();
});
