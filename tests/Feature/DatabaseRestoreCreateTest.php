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

// BUG: DatabaseRestoreCreate calls $this->form()->prompt('name', ...) directly in handle()
// without first calling loopUntilValid() or form()->errors(), which means Form::$errors
// is an uninitialized typed property. This causes a TypeError at runtime.
it('throws error due to uninitialized Form errors property', function () {
    Prompt::fake();

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbRestoreClusterResponse(), 200),
        CreateDatabaseRestoreRequest::class => MockResponse::make(dbRestoreCreatedResponse(), 200),
    ]);

    expect(fn () => $this->artisan('database-restore:create', [
        'cluster' => 'db-cluster-1',
        'name' => 'my-restore',
        '--snapshot' => 'snap-123',
        '--no-interaction' => true,
    ]))->toThrow(Error::class, 'must not be accessed before initialization');
});

it('resolves cluster for restore but hits same Form errors bug', function () {
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

    expect(fn () => $this->artisan('database-restore:create', [
        'cluster' => 'my-cluster',
        'name' => 'my-restore',
        '--snapshot' => 'snap-123',
        '--no-interaction' => true,
    ]))->toThrow(Error::class, 'must not be accessed before initialization');
});
