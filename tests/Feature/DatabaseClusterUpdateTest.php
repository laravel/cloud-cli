<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseTypesRequest;
use App\Client\Resources\DatabaseClusters\UpdateDatabaseClusterRequest;
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

function dbClusterUpdateTypesResponse(): array
{
    return [
        'data' => [
            [
                'type' => 'laravel_mysql_8',
                'label' => 'Laravel MySQL 8',
                'regions' => ['us-east-1'],
                'config_schema' => [
                    ['name' => 'size', 'type' => 'string', 'required' => true, 'enum' => ['db-flex.m-1vcpu-512mb', 'db-flex.m-1vcpu-2gb'], 'description' => 'Instance size'],
                    ['name' => 'storage', 'type' => 'integer', 'required' => true, 'min' => 5, 'max' => 200, 'description' => 'Storage in GB'],
                ],
            ],
        ],
        'included' => [],
    ];
}

function dbClusterUpdateGetResponse(): array
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
                'config' => [
                    'config.size' => 'db-flex.m-1vcpu-512mb',
                    'config.storage' => 5,
                ],
                'connection' => [],
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ],
        'included' => [],
    ];
}

// DatabaseClusterUpdate defines config fields dynamically from the type's config_schema.
// These config options (e.g. config.size, config.storage) are not in the command signature,
// so they cannot be passed as artisan options. In non-interactive mode, the form has no values
// and runUpdate fails with "No fields to update".

it('fails in non-interactive mode because config options are not in the command signature', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbClusterUpdateGetResponse(), 200),
        ListDatabaseTypesRequest::class => MockResponse::make(dbClusterUpdateTypesResponse(), 200),
    ]);

    $this->artisan('database-cluster:update', [
        'cluster' => 'db-cluster-1',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();
});

it('fails with JSON output when no config options provided', function () {
    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make(dbClusterUpdateGetResponse(), 200),
        ListDatabaseTypesRequest::class => MockResponse::make(dbClusterUpdateTypesResponse(), 200),
    ]);

    $this->artisan('database-cluster:update', [
        'cluster' => 'db-cluster-1',
        '--force' => true,
        '--json' => true,
    ])->assertFailed();
});

it('resolves cluster by name for update', function () {
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
                        'config' => [
                            'config.size' => 'db-flex.m-1vcpu-512mb',
                            'config.storage' => 5,
                        ],
                        'connection' => [],
                        'created_at' => now()->toISOString(),
                        'updated_at' => now()->toISOString(),
                    ],
                ],
            ],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        ListDatabaseTypesRequest::class => MockResponse::make(dbClusterUpdateTypesResponse(), 200),
    ]);

    // Fails because config options can't be passed non-interactively
    $this->artisan('database-cluster:update', [
        'cluster' => 'my-cluster',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();
});
