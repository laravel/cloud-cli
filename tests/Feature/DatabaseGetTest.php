<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\Databases\GetDatabaseRequest;
use App\Client\Resources\Databases\ListDatabasesRequest;
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

function dbGetClusterResponse(): array
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
        'included' => [],
    ];
}

function dbGetDatabaseResponse(): array
{
    return [
        'data' => [
            'id' => '1',
            'type' => 'databaseSchemas',
            'attributes' => [
                'name' => 'my_database',
                'created_at' => now()->toISOString(),
            ],
        ],
    ];
}

it('gets database details by cluster and database ID', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbGetClusterResponse(), 200),
        GetDatabaseRequest::class => MockResponse::make(dbGetDatabaseResponse(), 200),
    ]);

    $this->artisan('database:get', [
        'cluster' => 'db-cluster-1',
        'database' => '1',
    ])->assertSuccessful();
});

it('gets database details with JSON output', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbGetClusterResponse(), 200),
        GetDatabaseRequest::class => MockResponse::make(dbGetDatabaseResponse(), 200),
    ]);

    $this->artisan('database:get', [
        'cluster' => 'db-cluster-1',
        'database' => '1',
        '--json' => true,
    ])->assertSuccessful();
});

it('auto-selects sole database when only cluster given', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbGetClusterResponse(), 200),
        ListDatabasesRequest::class => MockResponse::make([
            'data' => [
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

    $this->artisan('database:get', [
        'cluster' => 'db-cluster-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('auto-selects sole cluster and sole database when no arguments given', function () {
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
        ListDatabasesRequest::class => MockResponse::make([
            'data' => [
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

    $this->artisan('database:get', [
        '--no-interaction' => true,
    ])->assertSuccessful();
});
