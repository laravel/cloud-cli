<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
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

function dbListClusterGetResponse(): array
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

function dbListDatabasesResponse(): array
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
            [
                'id' => '2',
                'type' => 'databaseSchemas',
                'attributes' => [
                    'name' => 'other-database',
                    'created_at' => now()->toISOString(),
                ],
            ],
        ],
        'included' => [],
        'links' => ['next' => null],
    ];
}

it('lists databases in a cluster by cluster ID', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbListClusterGetResponse(), 200),
        ListDatabasesRequest::class => MockResponse::make(dbListDatabasesResponse(), 200),
    ]);

    $this->artisan('database:list', [
        'cluster' => 'db-cluster-1',
    ])->assertSuccessful();
});

it('lists databases with JSON output', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbListClusterGetResponse(), 200),
        ListDatabasesRequest::class => MockResponse::make(dbListDatabasesResponse(), 200),
    ]);

    $this->artisan('database:list', [
        'cluster' => 'db-cluster-1',
        '--json' => true,
    ])->assertSuccessful();
});

it('outputs empty JSON when no databases found in non-interactive mode', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbListClusterGetResponse(), 200),
        ListDatabasesRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    // Empty list now returns failure
    $this->artisan('database:list', [
        'cluster' => 'db-cluster-1',
        '--json' => true,
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
        ListDatabasesRequest::class => MockResponse::make(dbListDatabasesResponse(), 200),
    ]);

    $this->artisan('database:list', [
        '--no-interaction' => true,
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
        ListDatabasesRequest::class => MockResponse::make(dbListDatabasesResponse(), 200),
    ]);

    $this->artisan('database:list', [
        'cluster' => 'my-cluster',
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

    $this->artisan('database:list', [
        'cluster' => 'nonexistent',
        '--no-interaction' => true,
    ])->assertFailed();
});
