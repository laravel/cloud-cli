<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Deployments\GetDeploymentLogsRequest;
use App\Client\Resources\Deployments\GetDeploymentRequest;
use App\Client\Resources\Deployments\InitiateDeploymentRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
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
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function createDeploymentResponse(string $status = 'pending', array $overrides = []): array
{
    return [
        'data' => array_merge([
            'id' => 'deploy-123',
            'type' => 'deployments',
            'attributes' => [
                'status' => $status,
                'started_at' => now()->toISOString(),
                'finished_at' => in_array($status, ['deployment.succeeded', 'deployment.failed', 'build.failed'])
                    ? now()->toISOString()
                    : null,
            ],
        ], $overrides),
    ];
}

function setupSuccessfulDeployMocks(): void
{
    MockClient::global([
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
        ], 200),

        InitiateDeploymentRequest::class => MockResponse::make(
            createDeploymentResponse('pending'),
            200,
        ),

        GetDeploymentRequest::class => MockResponse::make(
            createDeploymentResponse('deployment.succeeded'),
            200,
        ),
    ]);
}

it('deploys an application successfully when one app and one environment exist', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupSuccessfulDeployMocks();

    $this->artisan('deploy')
        ->assertSuccessful();
});

it('deploys using explicit application and environment arguments', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupSuccessfulDeployMocks();

    $this->artisan('deploy', [
        'application' => 'My App',
        'environment' => 'production',
    ])->assertSuccessful();
});

it('selects application when given by name argument with multiple apps', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [
                [
                    'id' => 'app-123',
                    'type' => 'applications',
                    'attributes' => [
                        'name' => 'My App Production',
                        'slug' => 'my-app-prod',
                        'region' => 'us-east-1',
                        'repository' => ['full_name' => 'user/my-app', 'default_branch' => 'main'],
                    ],
                    'relationships' => [
                        'organization' => ['data' => ['id' => 'org-1', 'type' => 'organizations']],
                        'environments' => ['data' => [['id' => 'env-1', 'type' => 'environments']]],
                        'defaultEnvironment' => ['data' => ['id' => 'env-1', 'type' => 'environments']],
                    ],
                ],
                [
                    'id' => 'app-456',
                    'type' => 'applications',
                    'attributes' => [
                        'name' => 'My App Staging',
                        'slug' => 'my-app-staging',
                        'region' => 'us-east-1',
                        'repository' => ['full_name' => 'user/my-app', 'default_branch' => 'main'],
                    ],
                    'relationships' => [
                        'organization' => ['data' => ['id' => 'org-1', 'type' => 'organizations']],
                        'environments' => ['data' => [['id' => 'env-2', 'type' => 'environments']]],
                        'defaultEnvironment' => ['data' => ['id' => 'env-2', 'type' => 'environments']],
                    ],
                ],
            ],
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org']],
                createEnvironmentResponse(),
                [
                    'id' => 'env-2',
                    'type' => 'environments',
                    'attributes' => [
                        'name' => 'staging',
                        'slug' => 'staging',
                        'vanity_domain' => 'my-app-staging.cloud.laravel.com',
                        'status' => 'running',
                        'php_major_version' => '8.3',
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),

        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [[
                'id' => 'env-2',
                'type' => 'environments',
                'attributes' => [
                    'name' => 'staging',
                    'slug' => 'staging',
                    'vanity_domain' => 'my-app-staging.cloud.laravel.com',
                    'status' => 'running',
                    'php_major_version' => '8.3',
                ],
            ]],
            'links' => ['next' => null],
        ], 200),

        GetEnvironmentRequest::class => MockResponse::make([
            'data' => [
                'id' => 'env-2',
                'type' => 'environments',
                'attributes' => [
                    'name' => 'staging',
                    'slug' => 'staging',
                    'vanity_domain' => 'my-app-staging.cloud.laravel.com',
                    'status' => 'running',
                    'php_major_version' => '8.3',
                ],
            ],
        ], 200),

        InitiateDeploymentRequest::class => MockResponse::make(
            createDeploymentResponse('pending'),
            200,
        ),

        GetDeploymentRequest::class => MockResponse::make(
            createDeploymentResponse('deployment.succeeded'),
            200,
        ),
    ]);

    $this->artisan('deploy', [
        'application' => 'My App Staging',
        'environment' => 'staging',
    ])->assertSuccessful();
});

it('selects environment by name when multiple environments exist', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [[
                'id' => 'app-123',
                'type' => 'applications',
                'attributes' => [
                    'name' => 'My App',
                    'slug' => 'my-app',
                    'region' => 'us-east-1',
                    'repository' => ['full_name' => 'user/my-app', 'default_branch' => 'main'],
                ],
                'relationships' => [
                    'organization' => ['data' => ['id' => 'org-1', 'type' => 'organizations']],
                    'environments' => ['data' => [
                        ['id' => 'env-1', 'type' => 'environments'],
                        ['id' => 'env-2', 'type' => 'environments'],
                    ]],
                    'defaultEnvironment' => ['data' => ['id' => 'env-1', 'type' => 'environments']],
                ],
            ]],
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org']],
                createEnvironmentResponse(),
                [
                    'id' => 'env-2',
                    'type' => 'environments',
                    'attributes' => [
                        'name' => 'staging',
                        'slug' => 'staging',
                        'vanity_domain' => 'my-app-staging.cloud.laravel.com',
                        'status' => 'running',
                        'php_major_version' => '8.3',
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),

        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [
                createEnvironmentResponse(),
                [
                    'id' => 'env-2',
                    'type' => 'environments',
                    'attributes' => [
                        'name' => 'staging',
                        'slug' => 'staging',
                        'vanity_domain' => 'my-app-staging.cloud.laravel.com',
                        'status' => 'running',
                        'php_major_version' => '8.3',
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),

        GetEnvironmentRequest::class => MockResponse::make([
            'data' => [
                'id' => 'env-2',
                'type' => 'environments',
                'attributes' => [
                    'name' => 'staging',
                    'slug' => 'staging',
                    'vanity_domain' => 'my-app-staging.cloud.laravel.com',
                    'status' => 'running',
                    'php_major_version' => '8.3',
                ],
            ],
        ], 200),

        InitiateDeploymentRequest::class => MockResponse::make(
            createDeploymentResponse('pending'),
            200,
        ),

        GetDeploymentRequest::class => MockResponse::make(
            createDeploymentResponse('deployment.succeeded'),
            200,
        ),
    ]);

    $this->artisan('deploy', [
        'application' => 'My App',
        'environment' => 'staging',
    ])->assertSuccessful();
});

it('deploys to specific application by ID', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupSuccessfulDeployMocks();

    $this->artisan('deploy', [
        'application' => 'app-123',
        'environment' => 'production',
    ])->assertSuccessful();
});

function setupFailedDeployMocks(string $failureStatus = 'build.failed', string $failureReason = 'Build failed', array $logs = []): void
{
    MockClient::global([
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
        ], 200),

        InitiateDeploymentRequest::class => MockResponse::make(
            createDeploymentResponse('pending'),
            200,
        ),

        GetDeploymentRequest::class => MockResponse::make(
            createDeploymentResponse($failureStatus, [
                'attributes' => [
                    'status' => $failureStatus,
                    'failure_reason' => $failureReason,
                    'started_at' => now()->toISOString(),
                    'finished_at' => now()->toISOString(),
                ],
            ]),
            200,
        ),

        GetDeploymentLogsRequest::class => MockResponse::make([
            'data' => $logs,
        ], 200),
    ]);
}

it('displays build logs when deployment fails with build error', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupFailedDeployMocks('build.failed', 'Build failed', [
        ['type' => 'build', 'timestamp' => now()->toISOString(), 'output' => "Installing dependencies...\nnpm ERR! Could not resolve dependency"],
    ]);

    $this->artisan('deploy', ['--no-interaction' => true])
        ->expectsOutputToContain('Build output:')
        ->expectsOutputToContain('npm ERR! Could not resolve dependency')
        ->assertFailed();
});

it('displays deploy logs when deployment fails with deploy error', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupFailedDeployMocks('deployment.failed', 'Deploy failed', [
        ['type' => 'deploy', 'timestamp' => now()->toISOString(), 'output' => "Running migrations...\nSQLSTATE[42S02]: Table not found"],
    ]);

    $this->artisan('deploy', ['--no-interaction' => true])
        ->expectsOutputToContain('Deploy output:')
        ->expectsOutputToContain('SQLSTATE[42S02]: Table not found')
        ->assertFailed();
});

it('handles failed deployment when logs endpoint returns empty', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupFailedDeployMocks('build.failed', 'Build failed', []);

    $this->artisan('deploy', ['--no-interaction' => true])
        ->assertFailed();
});
