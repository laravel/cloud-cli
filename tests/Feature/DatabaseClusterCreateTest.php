<?php

use App\Client\Resources\DatabaseClusters\CreateDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseTypesRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
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

function dbClusterCreateTypesResponse(): array
{
    return [
        'data' => [
            [
                'type' => 'laravel_mysql_8',
                'label' => 'Laravel MySQL 8',
                'regions' => ['us-east-1', 'us-east-2'],
                'config_schema' => [
                    ['name' => 'size', 'type' => 'string', 'required' => true, 'enum' => ['db-flex.m-1vcpu-512mb', 'db-flex.m-1vcpu-2gb']],
                    ['name' => 'storage', 'type' => 'integer', 'required' => true, 'min' => 5, 'max' => 200],
                ],
            ],
        ],
        'included' => [],
    ];
}

function dbClusterCreateRegionsResponse(): array
{
    return [
        'data' => [
            ['region' => 'us-east-1', 'label' => 'US East 1', 'flag' => 'us'],
            ['region' => 'us-east-2', 'label' => 'US East 2', 'flag' => 'us'],
        ],
        'included' => [],
    ];
}

function dbClusterCreateResponse(array $overrides = []): array
{
    return [
        'data' => array_merge([
            'id' => 'db-cluster-1',
            'type' => 'databaseClusters',
            'attributes' => [
                'name' => 'my-cluster',
                'type' => 'laravel_mysql_8',
                'status' => 'creating',
                'region' => 'us-east-1',
                'config' => ['size' => 'db-flex.m-1vcpu-512mb', 'storage' => 5],
                'connection' => [],
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ], $overrides),
        'included' => [],
    ];
}

it('creates a database cluster with non-interactive options', function () {
    Prompt::fake();

    MockClient::global([
        ListDatabaseTypesRequest::class => MockResponse::make(dbClusterCreateTypesResponse(), 200),
        ListRegionsRequest::class => MockResponse::make(dbClusterCreateRegionsResponse(), 200),
        CreateDatabaseClusterRequest::class => MockResponse::make(dbClusterCreateResponse(), 200),
    ]);

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'laravel_mysql_8',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('creates a database cluster with JSON output', function () {
    MockClient::global([
        ListDatabaseTypesRequest::class => MockResponse::make(dbClusterCreateTypesResponse(), 200),
        ListRegionsRequest::class => MockResponse::make(dbClusterCreateRegionsResponse(), 200),
        CreateDatabaseClusterRequest::class => MockResponse::make(dbClusterCreateResponse(), 200),
    ]);

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'laravel_mysql_8',
        '--region' => 'us-east-1',
        '--json' => true,
    ])->assertSuccessful();
});
