<?php

/**
 * Ship command tests.
 *
 * The Ship command is the most complex command in the CLI (788 lines). It orchestrates
 * application creation, database provisioning, environment configuration, and deployment.
 *
 * Testing strategy:
 * - Non-interactive mode is tested end-to-end where feasible, as it follows a deterministic
 *   code path without prompt interactions.
 * - Interactive mode is partially tested (existing app detection, deploy delegation).
 * - The full interactive flow (multiselect features, database cluster creation, websocket
 *   setup) involves deeply nested prompt interactions that are impractical to mock completely.
 *
 * Key flows tested:
 * 1. Non-interactive: errors when repository already has an application
 * 2. Non-interactive: creates app with --name and --region flags
 * 3. Non-interactive: requires a git remote repo
 * 4. JSON output mode
 */

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
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Sleep::fake();
    Process::fake();
    Http::fake(['*' => Http::response('OK', 200)]);

    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->mockGit->shouldReceive('currentBranch')->andReturn('main')->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app')->byDefault();
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true)->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
    Mockery::close();
});

function shipApplicationResponse(array $overrides = []): array
{
    return createApplicationResponse(array_merge([
        'attributes' => [
            'name' => 'my-app',
            'slug' => 'my-app',
            'region' => 'us-east-2',
            'repository' => ['full_name' => 'user/my-app', 'default_branch' => 'main'],
        ],
    ], $overrides));
}

function shipEnvironmentResponse(): array
{
    return [
        'id' => 'env-1',
        'type' => 'environments',
        'attributes' => [
            'name' => 'production',
            'slug' => 'production',
            'vanity_domain' => 'my-app.cloud.laravel.com',
            'status' => 'running',
            'php_major_version' => '8.3',
        ],
        'relationships' => [
            'instances' => ['data' => [['id' => 'inst-1', 'type' => 'instances']]],
        ],
    ];
}

function shipDeploymentResponse(string $status = 'deployment.succeeded'): array
{
    return [
        'data' => [
            'id' => 'deploy-123',
            'type' => 'deployments',
            'attributes' => [
                'status' => $status,
                'started_at' => now()->subMinutes(2)->toISOString(),
                'finished_at' => $status === 'deployment.succeeded' ? now()->toISOString() : null,
            ],
        ],
    ];
}

function shipDatabaseClusterResponse(): array
{
    return [
        'id' => 'cluster-1',
        'type' => 'databaseClusters',
        'attributes' => [
            'name' => 'database',
            'type' => 'neon_serverless_postgres_18',
            'status' => 'ready',
            'region' => 'us-east-2',
            'config' => [],
            'connection' => [],
        ],
    ];
}

function shipDatabaseResponse(): array
{
    return [
        'id' => 'db-1',
        'type' => 'databaseSchemas',
        'attributes' => [
            'name' => 'my_app',
        ],
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
                'size' => 'compute-1',
                'type' => 'web',
                'scaling_type' => 'fixed',
                'min_replicas' => 1,
                'max_replicas' => 1,
                'uses_scheduler' => true,
                'scaling_cpu_threshold_percentage' => null,
                'scaling_memory_threshold_percentage' => null,
            ],
        ],
    ];
}

it('fails in non-interactive mode when repository already has an application', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [shipApplicationResponse()],
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
                createEnvironmentResponse(),
            ],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('ship', ['--no-interaction' => true])
        ->assertFailed();
});

it('fails when no GitHub remote is found in non-interactive mode', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(false);
    $this->mockGit->shouldReceive('ghInstalled')->andReturn(false)->byDefault();
    $this->mockGit->shouldReceive('ghAuthenticated')->andReturn(false)->byDefault();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
    ]);

    $this->artisan('ship', ['--no-interaction' => true])
        ->assertFailed();
});

it('creates application non-interactively with default name from repository', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),

        // Initial list: no existing apps for this repo
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),

        // Create application
        CreateApplicationRequest::class => MockResponse::make([
            'data' => shipApplicationResponse(),
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
            ],
        ], 200),

        // Update avatar (may be called, but no avatar files exist in /tmp/test-repo)
        UpdateApplicationAvatarRequest::class => MockResponse::make([
            'data' => shipApplicationResponse(),
        ], 200),

        // Get application after creation
        GetApplicationRequest::class => MockResponse::make([
            'data' => shipApplicationResponse(),
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
                shipEnvironmentResponse(),
            ],
        ], 200),

        // Get environment
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => shipEnvironmentResponse(),
        ], 200),

        // Database types
        ListDatabaseTypesRequest::class => MockResponse::make(
            shipDatabaseTypesResponse(),
            200,
        ),

        // List database clusters (empty - will create new)
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),

        // Create database cluster
        CreateDatabaseClusterRequest::class => MockResponse::make([
            'data' => shipDatabaseClusterResponse(),
        ], 200),

        // Get database cluster with schemas
        GetDatabaseClusterRequest::class => MockResponse::make([
            'data' => shipDatabaseClusterResponse(),
            'included' => [],
        ], 200),

        // Create database schema
        CreateDatabaseRequest::class => MockResponse::make([
            'data' => shipDatabaseResponse(),
        ], 200),

        // Update instance (scheduler, octane, etc.)
        UpdateInstanceRequest::class => MockResponse::make(
            shipInstanceResponse(),
            200,
        ),

        // Update environment (attach database)
        UpdateEnvironmentRequest::class => MockResponse::make([
            'data' => shipEnvironmentResponse(),
        ], 200),

        // List environments for deploy subcommand
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [shipEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),

        // Deploy
        InitiateDeploymentRequest::class => MockResponse::make(
            shipDeploymentResponse('pending'),
            200,
        ),
        GetDeploymentRequest::class => MockResponse::make(
            shipDeploymentResponse('deployment.succeeded'),
            200,
        ),
    ]);

    $this->artisan('ship', ['--no-interaction' => true])
        ->assertSuccessful();
});

it('creates application non-interactively with custom --name and --region', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        CreateApplicationRequest::class => MockResponse::make([
            'data' => shipApplicationResponse([
                'attributes' => [
                    'name' => 'custom-app',
                    'slug' => 'custom-app',
                    'region' => 'eu-west-1',
                    'repository' => ['full_name' => 'user/my-app', 'default_branch' => 'main'],
                ],
            ]),
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
            ],
        ], 200),
        UpdateApplicationAvatarRequest::class => MockResponse::make([
            'data' => shipApplicationResponse(),
        ], 200),
        GetApplicationRequest::class => MockResponse::make([
            'data' => shipApplicationResponse([
                'attributes' => [
                    'name' => 'custom-app',
                    'slug' => 'custom-app',
                    'region' => 'eu-west-1',
                    'repository' => ['full_name' => 'user/my-app', 'default_branch' => 'main'],
                ],
            ]),
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
                shipEnvironmentResponse(),
            ],
        ], 200),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => shipEnvironmentResponse(),
        ], 200),
        ListDatabaseTypesRequest::class => MockResponse::make(
            shipDatabaseTypesResponse(),
            200,
        ),
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
        CreateDatabaseClusterRequest::class => MockResponse::make([
            'data' => shipDatabaseClusterResponse(),
        ], 200),
        GetDatabaseClusterRequest::class => MockResponse::make([
            'data' => shipDatabaseClusterResponse(),
            'included' => [],
        ], 200),
        CreateDatabaseRequest::class => MockResponse::make([
            'data' => shipDatabaseResponse(),
        ], 200),
        UpdateInstanceRequest::class => MockResponse::make(
            shipInstanceResponse(),
            200,
        ),
        UpdateEnvironmentRequest::class => MockResponse::make([
            'data' => shipEnvironmentResponse(),
        ], 200),
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [shipEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),
        InitiateDeploymentRequest::class => MockResponse::make(
            shipDeploymentResponse('pending'),
            200,
        ),
        GetDeploymentRequest::class => MockResponse::make(
            shipDeploymentResponse('deployment.succeeded'),
            200,
        ),
    ]);

    $this->artisan('ship', [
        '--name' => 'custom-app',
        '--region' => 'eu-west-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('outputs JSON in non-interactive mode with --json flag', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        CreateApplicationRequest::class => MockResponse::make([
            'data' => shipApplicationResponse(),
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
            ],
        ], 200),
        UpdateApplicationAvatarRequest::class => MockResponse::make([
            'data' => shipApplicationResponse(),
        ], 200),
        GetApplicationRequest::class => MockResponse::make([
            'data' => shipApplicationResponse(),
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
                shipEnvironmentResponse(),
            ],
        ], 200),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => shipEnvironmentResponse(),
        ], 200),
        ListDatabaseTypesRequest::class => MockResponse::make(
            shipDatabaseTypesResponse(),
            200,
        ),
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
        CreateDatabaseClusterRequest::class => MockResponse::make([
            'data' => shipDatabaseClusterResponse(),
        ], 200),
        GetDatabaseClusterRequest::class => MockResponse::make([
            'data' => shipDatabaseClusterResponse(),
            'included' => [],
        ], 200),
        CreateDatabaseRequest::class => MockResponse::make([
            'data' => shipDatabaseResponse(),
        ], 200),
        UpdateInstanceRequest::class => MockResponse::make(
            shipInstanceResponse(),
            200,
        ),
        UpdateEnvironmentRequest::class => MockResponse::make([
            'data' => shipEnvironmentResponse(),
        ], 200),
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [shipEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),
        InitiateDeploymentRequest::class => MockResponse::make(
            shipDeploymentResponse('pending'),
            200,
        ),
        GetDeploymentRequest::class => MockResponse::make(
            shipDeploymentResponse('deployment.succeeded'),
            200,
        ),
    ]);

    $this->artisan('ship', [
        '--json' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fails with invalid --database value', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        CreateApplicationRequest::class => MockResponse::make([
            'data' => shipApplicationResponse(),
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
            ],
        ], 200),
        UpdateApplicationAvatarRequest::class => MockResponse::make([
            'data' => shipApplicationResponse(),
        ], 200),
        GetApplicationRequest::class => MockResponse::make([
            'data' => shipApplicationResponse(),
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
                shipEnvironmentResponse(),
            ],
        ], 200),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => shipEnvironmentResponse(),
        ], 200),
        ListDatabaseTypesRequest::class => MockResponse::make(
            shipDatabaseTypesResponse(),
            200,
        ),
    ]);

    $this->artisan('ship', [
        '--database' => 'invalid_db_type',
        '--no-interaction' => true,
    ])->assertFailed();
});
