<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Deployments\GetDeploymentLogsRequest;
use App\Client\Resources\Deployments\GetDeploymentRequest;
use App\Client\Resources\Deployments\ListDeploymentsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
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

function deploymentLogsResponse(bool $buildAvailable = true, bool $deployAvailable = true): array
{
    return [
        'data' => [
            'build' => [
                'available' => $buildAvailable,
                'steps' => $buildAvailable ? [
                    [
                        'step' => 'install_dependencies',
                        'status' => 'completed',
                        'description' => 'Install dependencies',
                        'output' => "Installing packages...\nDone.",
                        'duration_ms' => 5000,
                        'time' => '2025-01-01T00:00:05Z',
                    ],
                    [
                        'step' => 'build_assets',
                        'status' => 'completed',
                        'description' => 'Build assets',
                        'output' => 'Assets compiled successfully.',
                        'duration_ms' => 3000,
                        'time' => '2025-01-01T00:00:08Z',
                    ],
                ] : [],
            ],
            'deploy' => [
                'available' => $deployAvailable,
                'steps' => $deployAvailable ? [
                    [
                        'step' => 'activate',
                        'status' => 'completed',
                        'description' => 'Activate deployment',
                        'output' => 'Deployment activated.',
                        'duration_ms' => 2000,
                        'time' => '2025-01-01T00:00:10Z',
                    ],
                ] : [],
            ],
        ],
        'meta' => [
            'deployment_status' => 'deployment.succeeded',
        ],
    ];
}

function logsDeploymentDataResponse(): array
{
    return [
        'id' => 'depl-123',
        'type' => 'deployments',
        'attributes' => [
            'status' => 'deployment.succeeded',
            'commit' => [
                'hash' => 'abc1234567890',
                'message' => 'Fix bug',
                'author' => 'Test User',
            ],
            'branch_name' => 'main',
            'started_at' => '2025-01-01T00:00:00.000000Z',
            'finished_at' => '2025-01-01T00:05:00.000000Z',
            'failure_reason' => null,
            'php_major_version' => '8.3',
        ],
        'relationships' => [
            'environment' => ['data' => ['id' => 'env-1', 'type' => 'environments']],
        ],
    ];
}

it('displays deployment logs by ID', function () {
    Prompt::fake();

    MockClient::global([
        GetDeploymentRequest::class => MockResponse::make([
            'data' => logsDeploymentDataResponse(),
            'included' => [createEnvironmentResponse()],
        ], 200),
        GetDeploymentLogsRequest::class => MockResponse::make(deploymentLogsResponse(), 200),
    ]);

    $this->artisan('deployment:logs', ['deployment' => 'depl-123'])
        ->assertSuccessful();
});

it('resolves deployment when no ID is given', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [createApplicationResponse()],
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org']],
                createEnvironmentResponse(),
            ],
            'links' => ['next' => null],
        ], 200),
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [createEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => createEnvironmentResponse(),
            'included' => [createApplicationResponse()],
        ], 200),
        ListDeploymentsRequest::class => MockResponse::make([
            'data' => [logsDeploymentDataResponse()],
            'included' => [createEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),
        GetDeploymentLogsRequest::class => MockResponse::make(deploymentLogsResponse(), 200),
    ]);

    $this->artisan('deployment:logs')
        ->assertSuccessful();
});

it('outputs logs as JSON when --json flag is used', function () {
    Prompt::fake();

    MockClient::global([
        GetDeploymentRequest::class => MockResponse::make([
            'data' => logsDeploymentDataResponse(),
            'included' => [createEnvironmentResponse()],
        ], 200),
        GetDeploymentLogsRequest::class => MockResponse::make(deploymentLogsResponse(), 200),
    ]);

    $this->artisan('deployment:logs', ['deployment' => 'depl-123', '--json' => true])
        ->assertSuccessful();
});

it('handles unavailable build logs', function () {
    Prompt::fake();

    MockClient::global([
        GetDeploymentRequest::class => MockResponse::make([
            'data' => logsDeploymentDataResponse(),
            'included' => [createEnvironmentResponse()],
        ], 200),
        GetDeploymentLogsRequest::class => MockResponse::make(
            deploymentLogsResponse(buildAvailable: false),
            200,
        ),
    ]);

    $this->artisan('deployment:logs', ['deployment' => 'depl-123'])
        ->assertSuccessful();
});

it('handles unavailable deploy logs', function () {
    Prompt::fake();

    MockClient::global([
        GetDeploymentRequest::class => MockResponse::make([
            'data' => logsDeploymentDataResponse(),
            'included' => [createEnvironmentResponse()],
        ], 200),
        GetDeploymentLogsRequest::class => MockResponse::make(
            deploymentLogsResponse(deployAvailable: false),
            200,
        ),
    ]);

    $this->artisan('deployment:logs', ['deployment' => 'depl-123'])
        ->assertSuccessful();
});
