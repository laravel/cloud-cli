<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\DatabaseRestores\CreateDatabaseRestoreRequest;
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

function dbRestoreClusterResponse(): array
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

function dbRestoreCreatedResponse(): array
{
    return [
        'data' => [
            'id' => 'db-cluster-restored',
            'type' => 'databaseClusters',
            'attributes' => [
                'name' => 'my-restore',
                'type' => 'laravel_mysql_8',
                'status' => 'creating',
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

it('creates a restore from a snapshot by cluster id', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbRestoreClusterResponse(), 200),
        CreateDatabaseRestoreRequest::class => MockResponse::make(dbRestoreCreatedResponse(), 200),
    ]);

    $this->artisan('database-restore:create', [
        'cluster' => 'db-cluster-1',
        'name' => 'my-restore',
        '--snapshot' => 'snap-123',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('creates a restore from a snapshot by cluster name', function () {
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
        CreateDatabaseRestoreRequest::class => MockResponse::make(dbRestoreCreatedResponse(), 200),
    ]);

    $this->artisan('database-restore:create', [
        'cluster' => 'my-cluster',
        'name' => 'my-restore',
        '--snapshot' => 'snap-123',
        '--no-interaction' => true,
    ])->assertSuccessful();
});
