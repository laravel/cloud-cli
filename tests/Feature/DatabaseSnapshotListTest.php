<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
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

function snapshotListClusterResponse(): array
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

it('lists database snapshots for a cluster', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotListClusterResponse(), 200),
        ListDatabaseSnapshotsRequest::class => MockResponse::make([
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
        ], 200),
    ]);

    $this->artisan('database-snapshot:list', [
        'cluster' => 'db-cluster-1',
    ])->assertSuccessful();
});

it('lists snapshots with JSON output', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotListClusterResponse(), 200),
        ListDatabaseSnapshotsRequest::class => MockResponse::make([
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
        ], 200),
    ]);

    $this->artisan('database-snapshot:list', [
        'cluster' => 'db-cluster-1',
        '--json' => true,
    ])->assertSuccessful();
});

it('outputs empty JSON when no snapshots found in non-interactive mode', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotListClusterResponse(), 200),
        ListDatabaseSnapshotsRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    // Empty list now returns failure
    $this->artisan('database-snapshot:list', [
        'cluster' => 'db-cluster-1',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('lists multiple snapshots', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotListClusterResponse(), 200),
        ListDatabaseSnapshotsRequest::class => MockResponse::make([
            'data' => [
                [
                    'id' => 'snap-1',
                    'type' => 'databaseSnapshots',
                    'attributes' => [
                        'name' => 'snapshot-1',
                        'status' => 'completed',
                        'created_at' => now()->toISOString(),
                    ],
                ],
                [
                    'id' => 'snap-2',
                    'type' => 'databaseSnapshots',
                    'attributes' => [
                        'name' => 'snapshot-2',
                        'status' => 'creating',
                        'created_at' => now()->toISOString(),
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('database-snapshot:list', [
        'cluster' => 'db-cluster-1',
    ])->assertSuccessful();
});

it('outputs empty JSON when no snapshots with --json flag', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotListClusterResponse(), 200),
        ListDatabaseSnapshotsRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('database-snapshot:list', [
        'cluster' => 'db-cluster-1',
        '--json' => true,
    ])->assertFailed();
});
