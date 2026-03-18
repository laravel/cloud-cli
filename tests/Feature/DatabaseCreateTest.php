<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\Databases\CreateDatabaseRequest;
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

function dbCreateClusterResponse(): array
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

function dbCreateDatabaseResponse(array $overrides = []): array
{
    return [
        'data' => array_merge([
            'id' => '1',
            'type' => 'databaseSchemas',
            'attributes' => [
                'name' => 'my-database',
                'created_at' => now()->toISOString(),
            ],
        ], $overrides),
        'included' => [],
    ];
}

it('creates a database in a cluster by cluster ID', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbCreateClusterResponse(), 200),
        CreateDatabaseRequest::class => MockResponse::make(dbCreateDatabaseResponse(), 200),
    ]);

    $this->artisan('database:create', [
        'cluster' => 'db-cluster-1',
        '--name' => 'my-database',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('creates a database with JSON output', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbCreateClusterResponse(), 200),
        CreateDatabaseRequest::class => MockResponse::make(dbCreateDatabaseResponse(), 200),
    ]);

    $this->artisan('database:create', [
        'cluster' => 'db-cluster-1',
        '--name' => 'my-database',
        '--json' => true,
    ])->assertSuccessful();
});

it('resolves cluster by name', function () {
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
        CreateDatabaseRequest::class => MockResponse::make(dbCreateDatabaseResponse(), 200),
    ]);

    $this->artisan('database:create', [
        'cluster' => 'my-cluster',
        '--name' => 'my-database',
        '--no-interaction' => true,
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

    $this->artisan('database:create', [
        'cluster' => 'nonexistent',
        '--name' => 'my-database',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('auto-selects sole cluster when no cluster argument given', function () {
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
        CreateDatabaseRequest::class => MockResponse::make(dbCreateDatabaseResponse(), 200),
    ]);

    $this->artisan('database:create', [
        '--name' => 'my-database',
        '--no-interaction' => true,
    ])->assertSuccessful();
});
