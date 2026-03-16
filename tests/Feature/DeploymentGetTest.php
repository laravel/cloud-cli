<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
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

function deploymentDataResponse(string $status = 'deployment.succeeded'): array
{
    return [
        'id' => 'depl-123',
        'type' => 'deployments',
        'attributes' => [
            'status' => $status,
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

function environmentWithAppResponse(): array
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
            'application' => ['data' => ['id' => 'app-123', 'type' => 'applications']],
        ],
    ];
}

it('gets a deployment by ID successfully', function () {
    Prompt::fake();

    MockClient::global([
        GetDeploymentRequest::class => MockResponse::make([
            'data' => deploymentDataResponse(),
            'included' => [createEnvironmentResponse()],
        ], 200),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => environmentWithAppResponse(),
            'included' => [
                createApplicationResponse(),
            ],
        ], 200),
    ]);

    $this->artisan('deployment:get', ['deployment' => 'depl-123'])
        ->assertSuccessful();
});

it('gets a deployment by resolving from environment when no ID given', function () {
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
        ListDeploymentsRequest::class => MockResponse::make([
            'data' => [deploymentDataResponse()],
            'included' => [createEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => environmentWithAppResponse(),
            'included' => [
                createApplicationResponse(),
            ],
        ], 200),
    ]);

    $this->artisan('deployment:get')
        ->assertSuccessful();
});
