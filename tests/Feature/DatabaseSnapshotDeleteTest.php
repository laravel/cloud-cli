<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseSnapshots\DeleteDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\ListDatabaseSnapshotsRequest;
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

function snapshotDeleteClusterResponse(): array
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

function snapshotDeleteSnapshotsListResponse(): array
{
    return [
        'data' => [
            [
                'id' => 'snap-1',
                'type' => 'databaseSnapshots',
                'attributes' => [
                    'name' => 'my-snapshot',
                    'status' => 'completed',
                    'created_at' => now()->toISOString(),
                ],
            ],
        ],
        'links' => ['next' => null],
    ];
}

it('deletes a database snapshot with force flag', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotDeleteClusterResponse(), 200),
        ListDatabaseSnapshotsRequest::class => MockResponse::make(snapshotDeleteSnapshotsListResponse(), 200),
        DeleteDatabaseSnapshotRequest::class => MockResponse::make([], 200),
    ]);

    $this->artisan('database-snapshot:delete', [
        'cluster' => 'db-cluster-1',
        'snapshot' => 'snap-1',
        '--force' => true,
    ])->assertSuccessful();
});

it('deletes a snapshot resolved by name', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotDeleteClusterResponse(), 200),
        ListDatabaseSnapshotsRequest::class => MockResponse::make(snapshotDeleteSnapshotsListResponse(), 200),
        DeleteDatabaseSnapshotRequest::class => MockResponse::make([], 200),
    ]);

    $this->artisan('database-snapshot:delete', [
        'cluster' => 'db-cluster-1',
        'snapshot' => 'my-snapshot',
        '--force' => true,
    ])->assertSuccessful();
});

it('cancels deletion without force in non-interactive mode', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotDeleteClusterResponse(), 200),
        ListDatabaseSnapshotsRequest::class => MockResponse::make(snapshotDeleteSnapshotsListResponse(), 200),
    ]);

    $this->artisan('database-snapshot:delete', [
        'cluster' => 'db-cluster-1',
        'snapshot' => 'snap-1',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('fails when no snapshots found', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotDeleteClusterResponse(), 200),
        ListDatabaseSnapshotsRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('database-snapshot:delete', [
        'cluster' => 'db-cluster-1',
        'snapshot' => 'nonexistent',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();
});
