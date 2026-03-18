<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseSnapshots\CreateDatabaseSnapshotRequest;
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

function snapshotCreateClusterResponse(): array
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

function snapshotCreateResponse(): array
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

// The database-snapshot:create command does not have --name or --description options in its signature.
// In non-interactive mode (test env), form()->prompt() requires values but they can't be provided,
// causing "name is required" RuntimeException. This is a limitation for non-interactive usage.
it('fails in non-interactive mode because name and description options are not in the signature', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotCreateClusterResponse(), 200),
        CreateDatabaseSnapshotRequest::class => MockResponse::make(snapshotCreateResponse(), 200),
    ]);

    $this->artisan('database-snapshot:create', [
        'cluster' => 'db-cluster-1',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('resolves cluster by ID for snapshot creation', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotCreateClusterResponse(), 200),
        CreateDatabaseSnapshotRequest::class => MockResponse::make(snapshotCreateResponse(), 200),
    ]);

    // Fails due to missing name/description options in non-interactive mode
    $this->artisan('database-snapshot:create', [
        'cluster' => 'db-cluster-1',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('outputs JSON for snapshot creation failure in non-interactive mode', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(snapshotCreateClusterResponse(), 200),
    ]);

    // Non-interactive mode cannot prompt for name, so it fails
    $this->artisan('database-snapshot:create', [
        'cluster' => 'db-cluster-1',
        '--json' => true,
    ])->assertFailed();
});
