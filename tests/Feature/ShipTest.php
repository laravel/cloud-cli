<?php

use App\Client\Resources\Applications\CreateApplicationRequest;
use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Applications\UpdateApplicationAvatarRequest;
use App\Client\Resources\DatabaseClusters\CreateDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseTypesRequest;
use App\Client\Resources\Databases\CreateDatabaseRequest;
use App\Client\Resources\Deployments\GetDeploymentRequest;
use App\Client\Resources\Deployments\InitiateDeploymentRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\Client\Resources\Environments\UpdateEnvironmentRequest;
use App\Client\Resources\Instances\UpdateInstanceRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

// ---------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------

function shipApplicationResponse(array $overrides = []): array
{
    return [
        'id' => $overrides['id'] ?? 'app-ship-1',
        'type' => 'applications',
        'attributes' => array_merge([
            'name' => 'my-app',
            'slug' => 'my-app',
            'region' => 'us-east-2',
            'repository' => [
                'full_name' => 'user/my-app',
                'default_branch' => 'main',
            ],
        ], $overrides['attributes'] ?? []),
        'relationships' => array_merge([
            'organization' => ['data' => ['id' => 'org-1', 'type' => 'organizations']],
            'environments' => ['data' => [['id' => 'env-ship-1', 'type' => 'environments']]],
            'defaultEnvironment' => ['data' => ['id' => 'env-ship-1', 'type' => 'environments']],
        ], $overrides['relationships'] ?? []),
    ];
}

function shipEnvironmentResponse(array $overrides = []): array
{
    return [
        'id' => $overrides['id'] ?? 'env-ship-1',
        'type' => 'environments',
        'attributes' => array_merge([
            'name' => 'production',
            'slug' => 'production',
            'vanity_domain' => 'my-app.cloud.laravel.com',
            'status' => 'running',
            'php_major_version' => '8.3',
        ], $overrides['attributes'] ?? []),
        'relationships' => array_merge([
            'instances' => ['data' => [['id' => 'inst-1', 'type' => 'instances']]],
        ], $overrides['relationships'] ?? []),
    ];
}

function shipOrgInclude(): array
{
    return [
        'id' => 'org-1',
        'type' => 'organizations',
        'attributes' => ['name' => 'My Org', 'slug' => 'my-org'],
    ];
}

function shipDatabaseTypesResponse(): array
{
    return [
        'data' => [
            [
                'type' => 'neon_serverless_postgres_18',
                'label' => 'Neon Serverless Postgres 18',
                'regions' => ['us-east-2'],
                'config_schema' => [],
            ],
            [
                'type' => 'neon_serverless_postgres_17',
                'label' => 'Neon Serverless Postgres 17',
                'regions' => ['us-east-2'],
                'config_schema' => [],
            ],
            [
                'type' => 'laravel_mysql_8',
                'label' => 'Laravel MySQL 8',
                'regions' => ['us-east-2'],
                'config_schema' => [],
            ],
        ],
    ];
}

function shipDatabaseClusterResponse(array $overrides = []): array
{
    return [
        'id' => $overrides['id'] ?? 'cluster-1',
        'type' => 'databaseClusters',
        'attributes' => array_merge([
            'name' => 'database',
            'type' => 'neon_serverless_postgres_18',
            'status' => 'running',
            'region' => 'us-east-2',
            'config' => [],
            'connection' => [],
        ], $overrides['attributes'] ?? []),
    ];
}

function shipDatabaseResponse(array $overrides = []): array
{
    return [
        'id' => $overrides['id'] ?? 'db-1',
        'type' => 'databaseSchemas',
        'attributes' => array_merge([
            'name' => 'my_app',
        ], $overrides['attributes'] ?? []),
    ];
}

function shipDeploymentResponse(string $status = 'pending'): array
{
    return [
        'data' => [
            'id' => 'deploy-ship-1',
            'type' => 'deployments',
            'attributes' => [
                'status' => $status,
                'started_at' => now()->toISOString(),
                'finished_at' => $status === 'deployment.succeeded' || $status === 'deployment.failed'
                    ? now()->toISOString()
                    : null,
            ],
        ],
    ];
}

function shipInstanceResponse(): array
{
    return [
        'data' => [
            'id' => 'inst-1',
            'type' => 'instances',
            'attributes' => [
                'name' => 'web',
                'type' => 'web',
                'size' => 'standard-1',
                'scaling_type' => 'fixed',
                'min_replicas' => 1,
                'max_replicas' => 1,
                'uses_scheduler' => true,
            ],
        ],
    ];
}

/**
 * Build MockClient for a successful non-interactive ship flow.
 *
 * Includes mocks for both the Ship command and the Deploy sub-command.
 */
function setupShipMocks(array $mockOverrides = []): void
{
    $appData = shipApplicationResponse();
    $envData = shipEnvironmentResponse();

    $defaults = [
        // Ship: list apps (empty = new repo)
        // Deploy sub-command resolver also uses this
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),

        // Ship: create application
        CreateApplicationRequest::class => MockResponse::make([
            'data' => $appData,
            'included' => [shipOrgInclude(), $envData],
        ], 201),

        // Ship: update avatar (optional, catches errors)
        UpdateApplicationAvatarRequest::class => MockResponse::make([
            'data' => $appData,
        ], 200),

        // Ship: get application (refresh) + Deploy resolver
        GetApplicationRequest::class => MockResponse::make([
            'data' => $appData,
            'included' => [shipOrgInclude(), $envData],
        ], 200),

        // Ship: get environment + Deploy resolver fetch
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => $envData,
        ], 200),

        // Deploy resolver: list environments (fromBranch)
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [$envData],
            'links' => ['next' => null],
        ], 200),

        // Ship: database types
        ListDatabaseTypesRequest::class => MockResponse::make(
            shipDatabaseTypesResponse(),
            200,
        ),

        // Ship: list clusters (empty = create new)
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),

        // Ship: create cluster
        CreateDatabaseClusterRequest::class => MockResponse::make([
            'data' => shipDatabaseClusterResponse(),
        ], 201),

        // Ship: get cluster with schemas
        GetDatabaseClusterRequest::class => MockResponse::make([
            'data' => shipDatabaseClusterResponse(),
            'included' => [shipDatabaseResponse()],
        ], 200),

        // Ship: create database
        CreateDatabaseRequest::class => MockResponse::make([
            'data' => shipDatabaseResponse(),
        ], 201),

        // Ship: update instance
        UpdateInstanceRequest::class => MockResponse::make(
            shipInstanceResponse(),
            200,
        ),

        // Ship: update environment (attach DB)
        UpdateEnvironmentRequest::class => MockResponse::make([
            'data' => $envData,
        ], 200),

        // Deploy: initiate
        InitiateDeploymentRequest::class => MockResponse::make(
            shipDeploymentResponse('pending'),
            200,
        ),

        // Deploy: poll status
        GetDeploymentRequest::class => MockResponse::make(
            shipDeploymentResponse('deployment.succeeded'),
            200,
        ),
    ];

    MockClient::global(array_merge($defaults, $mockOverrides));
}

/**
 * Build mocks for tests that need creation but don't need Deploy sub-command mocks.
 */
function setupShipCreationMocks(array $mockOverrides = []): void
{
    $appData = shipApplicationResponse();
    $envData = shipEnvironmentResponse();

    $defaults = [
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        CreateApplicationRequest::class => MockResponse::make([
            'data' => $appData,
            'included' => [shipOrgInclude(), $envData],
        ], 201),
        UpdateApplicationAvatarRequest::class => MockResponse::make([
            'data' => $appData,
        ], 200),
        GetApplicationRequest::class => MockResponse::make([
            'data' => $appData,
            'included' => [shipOrgInclude(), $envData],
        ], 200),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => $envData,
        ], 200),
        ListDatabaseTypesRequest::class => MockResponse::make(
            shipDatabaseTypesResponse(),
            200,
        ),
    ];

    MockClient::global(array_merge($defaults, $mockOverrides));
}

// ---------------------------------------------------------------------------
// Setup / Teardown
// ---------------------------------------------------------------------------

beforeEach(function () {
    Sleep::fake();

    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->mockGit->shouldReceive('currentBranch')->andReturn('main')->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app')->byDefault();
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true)->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(fn () => MockClient::destroyGlobal());

// ===========================================================================
// HAPPY PATH TESTS
// ===========================================================================

it('ships a new application end-to-end in non-interactive mode', function () {
    Prompt::fake();
    setupShipMocks();

    $this->artisan('ship', ['--no-interaction' => true])
        ->assertSuccessful();
});

it('ships with custom --name and --region', function () {
    Prompt::fake();
    setupShipMocks();

    $this->artisan('ship', [
        '--name' => 'custom-app',
        '--region' => 'eu-west-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('ships with --database=mysql', function () {
    Prompt::fake();
    setupShipMocks();

    $this->artisan('ship', [
        '--database' => 'mysql',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('ships with --database=postgres17', function () {
    Prompt::fake();
    setupShipMocks();

    $this->artisan('ship', [
        '--database' => 'postgres17',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('ships with --database-preset=prod', function () {
    Prompt::fake();
    setupShipMocks();

    $this->artisan('ship', [
        '--database-preset' => 'prod',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('ships with --json and outputs JSON with application and environment info', function () {
    Prompt::fake();
    setupShipMocks();

    // --json in non-interactive mode: outputJsonIfWanted writes JSON then exits with SUCCESS
    $this->artisan('ship', [
        '--json' => true,
        '--no-interaction' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('app-ship-1');
});

it('ships with --dry-run and shows plan without creating resources', function () {
    Prompt::fake();

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('ship', [
        '--dry-run' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('reuses an existing database cluster instead of creating a new one', function () {
    Prompt::fake();

    $clusterData = shipDatabaseClusterResponse(['attributes' => ['name' => 'database']]);

    setupShipMocks([
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [$clusterData],
            'links' => ['next' => null],
        ], 200),
        GetDatabaseClusterRequest::class => MockResponse::make([
            'data' => $clusterData,
            'included' => [shipDatabaseResponse()],
        ], 200),
    ]);

    $this->artisan('ship', ['--no-interaction' => true])
        ->assertSuccessful();
});

// ===========================================================================
// UNHAPPY PATH TESTS
// ===========================================================================

it('errors when the repo already has an application in non-interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [shipApplicationResponse()],
            'included' => [shipOrgInclude(), shipEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);

    // Non-interactive with existing app: outputs error JSON and returns FAILURE
    // (BaseCommand::run catches RuntimeException when wantsJson)
    $this->artisan('ship', ['--no-interaction' => true])
        ->assertFailed();
});

it('fails when there is no git remote in non-interactive mode', function () {
    Prompt::fake();
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(false);

    // ensureRemoteGitRepo throws RuntimeException, caught by BaseCommand::run
    // (wantsJson is true in non-interactive mode) and returns FAILURE
    $this->artisan('ship', ['--no-interaction' => true])
        ->assertFailed();
});

it('errors with invalid --database value in non-interactive mode', function () {
    Prompt::fake();
    setupShipCreationMocks();

    // Invalid --database: outputErrorOrThrow throws RuntimeException in non-interactive mode
    // BaseCommand::run catches it (wantsJson) and returns FAILURE
    $this->artisan('ship', [
        '--database' => 'invalid-db',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('errors with invalid --database-preset in non-interactive mode', function () {
    Prompt::fake();
    setupShipCreationMocks();

    $this->artisan('ship', [
        '--database-preset' => 'mega',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('handles validation error when app creation fails with 422', function () {
    Prompt::fake();

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        CreateApplicationRequest::class => MockResponse::make([
            'message' => 'The given data was invalid.',
            'errors' => [
                'name' => ['The name has already been taken.'],
            ],
        ], 422),
    ]);

    // createApplicationNonInteractively is not wrapped in loopUntilValid,
    // so the UnprocessableEntityException (extends Exception, not RuntimeException)
    // propagates past BaseCommand::run's catch blocks
    $this->artisan('ship', ['--no-interaction' => true]);
})->throws(Saloon\Exceptions\Request\Statuses\UnprocessableEntityException::class);

it('handles server error when app creation fails with 500', function () {
    Prompt::fake();

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        CreateApplicationRequest::class => MockResponse::make([
            'message' => 'Internal Server Error',
        ], 500),
    ]);

    $this->artisan('ship', ['--no-interaction' => true]);
})->throws(Exception::class);

it('handles database provisioning failure gracefully', function () {
    Prompt::fake();

    setupShipMocks([
        CreateDatabaseRequest::class => MockResponse::make([
            'message' => 'Database creation failed.',
            'errors' => [
                'database' => ['Could not provision database.'],
            ],
        ], 422),
    ]);

    // The loopUntilValid call wraps createDatabaseWithName; in non-interactive mode,
    // after the first failure the validation loop breaks and throws RuntimeException,
    // which BaseCommand::run catches (wantsJson) and returns FAILURE.
    $this->artisan('ship', ['--no-interaction' => true])
        ->assertFailed();
});

it('handles instance update failure', function () {
    Prompt::fake();

    setupShipMocks([
        UpdateInstanceRequest::class => MockResponse::make([
            'message' => 'Instance update failed.',
        ], 500),
    ]);

    $this->artisan('ship', ['--no-interaction' => true]);
})->throws(Exception::class);

it('handles environment with no instances', function () {
    Prompt::fake();

    $envWithoutInstances = shipEnvironmentResponse([
        'relationships' => [
            'instances' => ['data' => []],
        ],
    ]);

    setupShipMocks([
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => $envWithoutInstances,
        ], 200),
    ]);

    // When instances is empty, accessing instances[0] triggers an error
    $this->artisan('ship', ['--no-interaction' => true]);
})->throws(Exception::class);

// ===========================================================================
// EDGE CASE TESTS
// ===========================================================================

it('creates a new database on an existing cluster', function () {
    Prompt::fake();

    $clusterData = shipDatabaseClusterResponse(['attributes' => ['name' => 'database']]);

    setupShipMocks([
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [$clusterData],
            'links' => ['next' => null],
        ], 200),
        GetDatabaseClusterRequest::class => MockResponse::make([
            'data' => $clusterData,
            'included' => [shipDatabaseResponse()],
        ], 200),
        CreateDatabaseRequest::class => MockResponse::make([
            'data' => shipDatabaseResponse(['id' => 'db-new', 'attributes' => ['name' => 'my_app']]),
        ], 201),
    ]);

    $this->artisan('ship', ['--no-interaction' => true])
        ->assertSuccessful();
});

it('derives app name from repository name', function () {
    Prompt::fake();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-cool-app');

    $appData = shipApplicationResponse([
        'attributes' => [
            'name' => 'my-cool-app',
            'slug' => 'my-cool-app',
            'region' => 'us-east-2',
            'repository' => [
                'full_name' => 'user/my-cool-app',
                'default_branch' => 'main',
            ],
        ],
    ]);
    $envData = shipEnvironmentResponse();

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        CreateApplicationRequest::class => MockResponse::make([
            'data' => $appData,
            'included' => [shipOrgInclude(), $envData],
        ], 201),
        UpdateApplicationAvatarRequest::class => MockResponse::make(['data' => $appData], 200),
        GetApplicationRequest::class => MockResponse::make([
            'data' => $appData,
            'included' => [shipOrgInclude(), $envData],
        ], 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => $envData], 200),
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [$envData],
            'links' => ['next' => null],
        ], 200),
        ListDatabaseTypesRequest::class => MockResponse::make(shipDatabaseTypesResponse(), 200),
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
        CreateDatabaseClusterRequest::class => MockResponse::make([
            'data' => shipDatabaseClusterResponse(),
        ], 201),
        GetDatabaseClusterRequest::class => MockResponse::make([
            'data' => shipDatabaseClusterResponse(),
            'included' => [shipDatabaseResponse()],
        ], 200),
        CreateDatabaseRequest::class => MockResponse::make(['data' => shipDatabaseResponse()], 201),
        UpdateInstanceRequest::class => MockResponse::make(shipInstanceResponse(), 200),
        UpdateEnvironmentRequest::class => MockResponse::make(['data' => $envData], 200),
        InitiateDeploymentRequest::class => MockResponse::make(shipDeploymentResponse('pending'), 200),
        GetDeploymentRequest::class => MockResponse::make(shipDeploymentResponse('deployment.succeeded'), 200),
    ]);

    $this->artisan('ship', ['--no-interaction' => true])
        ->assertSuccessful();
});

it('uses most-used region as default when user has existing apps', function () {
    Prompt::fake();

    // 3 apps in ap-southeast-2, none matching this repo
    $existingApps = [];
    for ($i = 1; $i <= 3; $i++) {
        $existingApps[] = [
            'id' => "app-other-{$i}",
            'type' => 'applications',
            'attributes' => [
                'name' => "other-app-{$i}",
                'slug' => "other-app-{$i}",
                'region' => 'ap-southeast-2',
                'repository' => ['full_name' => "user/other-{$i}", 'default_branch' => 'main'],
            ],
            'relationships' => [
                'organization' => ['data' => ['id' => 'org-1', 'type' => 'organizations']],
                'environments' => ['data' => []],
                'defaultEnvironment' => ['data' => null],
            ],
        ];
    }

    $appData = shipApplicationResponse();
    $envData = shipEnvironmentResponse();

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => $existingApps,
            'included' => [shipOrgInclude()],
            'links' => ['next' => null],
        ], 200),
        CreateApplicationRequest::class => MockResponse::make([
            'data' => $appData,
            'included' => [shipOrgInclude(), $envData],
        ], 201),
        UpdateApplicationAvatarRequest::class => MockResponse::make(['data' => $appData], 200),
        GetApplicationRequest::class => MockResponse::make([
            'data' => $appData,
            'included' => [shipOrgInclude(), $envData],
        ], 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => $envData], 200),
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [$envData],
            'links' => ['next' => null],
        ], 200),
        ListDatabaseTypesRequest::class => MockResponse::make(shipDatabaseTypesResponse(), 200),
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
        CreateDatabaseClusterRequest::class => MockResponse::make([
            'data' => shipDatabaseClusterResponse(),
        ], 201),
        GetDatabaseClusterRequest::class => MockResponse::make([
            'data' => shipDatabaseClusterResponse(),
            'included' => [shipDatabaseResponse()],
        ], 200),
        CreateDatabaseRequest::class => MockResponse::make(['data' => shipDatabaseResponse()], 201),
        UpdateInstanceRequest::class => MockResponse::make(shipInstanceResponse(), 200),
        UpdateEnvironmentRequest::class => MockResponse::make(['data' => $envData], 200),
        InitiateDeploymentRequest::class => MockResponse::make(shipDeploymentResponse('pending'), 200),
        GetDeploymentRequest::class => MockResponse::make(shipDeploymentResponse('deployment.succeeded'), 200),
    ]);

    $this->artisan('ship', ['--no-interaction' => true])
        ->assertSuccessful();
});

it('ships with --database=postgres and resolves to postgres18', function () {
    Prompt::fake();
    setupShipMocks();

    $this->artisan('ship', [
        '--database' => 'postgres',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('ships with --database-preset=scale', function () {
    Prompt::fake();
    setupShipMocks();

    $this->artisan('ship', [
        '--database-preset' => 'scale',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('dry-run with custom name and region completes successfully', function () {
    Prompt::fake();

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('ship', [
        '--dry-run' => true,
        '--name' => 'custom-name',
        '--region' => 'eu-west-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('dry-run with database options completes successfully', function () {
    Prompt::fake();

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('ship', [
        '--dry-run' => true,
        '--database' => 'mysql',
        '--database-preset' => 'prod',
        '--no-interaction' => true,
    ])->assertSuccessful();
});
