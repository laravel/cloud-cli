<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseSnapshots\GetDatabaseSnapshotRequest;
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

function snapshotGetClusterResponse(): array
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

function snapshotGetSnapshotsListResponse(): array
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

function snapshotGetDetailResponse(): array
{
    return [
        'data' => [
            'id' => 'snap-1',
            'type' => 'databaseSnapshots',
            'attributes' => [
                'name' => 'my-snapshot',
                'status' => 'completed',
                'created_at' => now()->toISOString(),
            ],
        ],
    ];
}

it('gets database snapshot details by ID', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotGetClusterResponse(), 200),
        ListDatabaseSnapshotsRequest::class => MockResponse::make(snapshotGetSnapshotsListResponse(), 200),
        GetDatabaseSnapshotRequest::class => MockResponse::make(snapshotGetDetailResponse(), 200),
    ]);

    $this->artisan('database-snapshot:get', [
        'cluster' => 'db-cluster-1',
        'snapshot' => 'snap-1',
    ])->assertSuccessful();
});

it('gets database snapshot details with JSON output', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotGetClusterResponse(), 200),
        ListDatabaseSnapshotsRequest::class => MockResponse::make(snapshotGetSnapshotsListResponse(), 200),
        GetDatabaseSnapshotRequest::class => MockResponse::make(snapshotGetDetailResponse(), 200),
    ]);

    $this->artisan('database-snapshot:get', [
        'cluster' => 'db-cluster-1',
        'snapshot' => 'snap-1',
        '--json' => true,
    ])->assertSuccessful();
});

it('resolves snapshot by name', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotGetClusterResponse(), 200),
        ListDatabaseSnapshotsRequest::class => MockResponse::make(snapshotGetSnapshotsListResponse(), 200),
        GetDatabaseSnapshotRequest::class => MockResponse::make(snapshotGetDetailResponse(), 200),
    ]);

    $this->artisan('database-snapshot:get', [
        'cluster' => 'db-cluster-1',
        'snapshot' => 'my-snapshot',
    ])->assertSuccessful();
});

it('auto-selects sole snapshot when no snapshot argument given', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotGetClusterResponse(), 200),
        ListDatabaseSnapshotsRequest::class => MockResponse::make(snapshotGetSnapshotsListResponse(), 200),
        GetDatabaseSnapshotRequest::class => MockResponse::make(snapshotGetDetailResponse(), 200),
    ]);

    $this->artisan('database-snapshot:get', [
        'cluster' => 'db-cluster-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fails when no snapshots found', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotGetClusterResponse(), 200),
        ListDatabaseSnapshotsRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('database-snapshot:get', [
        'cluster' => 'db-cluster-1',
        '--no-interaction' => true,
    ])->assertFailed();
});
