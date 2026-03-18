<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\Databases\DeleteDatabaseRequest;
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

function dbDeleteClusterResponse(): array
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
            ],
        ],
        'included' => [],
    ];
}

function dbDeleteDatabaseGetResponse(): array
{
    return [
        'data' => [
            'id' => '1',
            'type' => 'databaseSchemas',
            'attributes' => [
                'name' => 'my-database',
                'created_at' => now()->toISOString(),
            ],
        ],
        'included' => [],
    ];
}

function dbDeleteDatabaseListResponse(): array
{
    return [
        'data' => [
            [
                'id' => '1',
                'type' => 'databaseSchemas',
                'attributes' => [
                    'name' => 'my-database',
                    'created_at' => now()->toISOString(),
                ],
            ],
        ],
        'included' => [],
        'links' => ['next' => null],
    ];
}

// BUG: DatabaseDelete catches Throwable which also catches CommandExitException thrown by
// outputJsonIfWanted(). In non-interactive mode (all test environments), outputJsonIfWanted()
// throws CommandExitException(SUCCESS) after outputting JSON, but the catch(Throwable) block
// treats it as an error and returns FAILURE. The delete itself succeeds, but the exit code
// is wrong. See BUGS_FOUND.md for details.

it('deletes a database successfully', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbDeleteClusterResponse(), 200),
        ListDatabasesRequest::class => MockResponse::make([
            'data' => [
                [
                    'id' => '1',
                    'type' => 'databaseSchemas',
                    'attributes' => [
                        'name' => 'my-database',
                        'created_at' => now()->toISOString(),
                    ],
                ],
            ],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        DeleteDatabaseRequest::class => MockResponse::make([], 200),
    ]);

    $this->artisan('database:delete', [
        'cluster' => 'db-cluster-1',
        'database' => 'my-database',
        '--force' => true,
    ])->assertSuccessful();
});

it('deletes a database by numeric ID', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbDeleteClusterResponse(), 200),
        GetDatabaseRequest::class => MockResponse::make(dbDeleteDatabaseGetResponse(), 200),
        DeleteDatabaseRequest::class => MockResponse::make([], 200),
    ]);

    $this->artisan('database:delete', [
        'cluster' => 'db-cluster-1',
        'database' => '1',
        '--force' => true,
    ])->assertSuccessful();
});

it('outputs JSON when deleting with --json', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbDeleteClusterResponse(), 200),
        GetDatabaseRequest::class => MockResponse::make(dbDeleteDatabaseGetResponse(), 200),
        DeleteDatabaseRequest::class => MockResponse::make([], 200),
    ]);

    $this->artisan('database:delete', [
        'cluster' => 'db-cluster-1',
        'database' => '1',
        '--force' => true,
        '--json' => true,
    ])->assertSuccessful();
});

it('fails when cluster not found', function () {
    Prompt::fake();

    MockClient::global([
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('database:delete', [
        'cluster' => 'nonexistent',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();
});

it('fails when no databases found in cluster', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbDeleteClusterResponse(), 200),
        ListDatabasesRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('database:delete', [
        'cluster' => 'db-cluster-1',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();
});
