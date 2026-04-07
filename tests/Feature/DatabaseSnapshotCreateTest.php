<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseSnapshots\CreateDatabaseSnapshotRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Sleep;
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

function snapshotClusterResponse(): array
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

function snapshotResponse(): array
{
    return [
        'data' => [
            'id' => 'snap-1',
            'type' => 'databaseSnapshots',
            'attributes' => [
                'name' => 'my-snapshot',
                'status' => 'creating',
                'created_at' => now()->toISOString(),
            ],
        ],
    ];
}

it('creates a snapshot with both --name and --description in non-interactive mode', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotClusterResponse(), 200),
        CreateDatabaseSnapshotRequest::class => MockResponse::make(snapshotResponse(), 200),
    ]);

    $this->artisan('database-snapshot:create', [
        'cluster' => 'db-cluster-1',
        '--name' => 'daily-backup',
        '--description' => 'Scheduled backup',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('creates a snapshot with --name only, description defaults to empty string', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotClusterResponse(), 200),
        CreateDatabaseSnapshotRequest::class => MockResponse::make(snapshotResponse(), 200),
    ]);

    $this->artisan('database-snapshot:create', [
        'cluster' => 'db-cluster-1',
        '--name' => 'name-only-snapshot',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fails in non-interactive mode when --name is not provided', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotClusterResponse(), 200),
    ]);

    $this->artisan('database-snapshot:create', [
        'cluster' => 'db-cluster-1',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('outputs JSON when --json flag is used with options', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotClusterResponse(), 200),
        CreateDatabaseSnapshotRequest::class => MockResponse::make(snapshotResponse(), 200),
    ]);

    $this->artisan('database-snapshot:create', [
        'cluster' => 'db-cluster-1',
        '--name' => 'json-test',
        '--description' => 'JSON output test',
        '--json' => true,
    ])->assertSuccessful();
});

it('works with the db-snapshot:create alias', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotClusterResponse(), 200),
        CreateDatabaseSnapshotRequest::class => MockResponse::make(snapshotResponse(), 200),
    ]);

    $this->artisan('db-snapshot:create', [
        'cluster' => 'db-cluster-1',
        '--name' => 'alias-test',
        '--description' => 'Alias test',
        '--no-interaction' => true,
    ])->assertSuccessful();
});
