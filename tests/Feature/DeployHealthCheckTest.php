<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Deployments\GetDeploymentRequest;
use App\Client\Resources\Deployments\InitiateDeploymentRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Facades\Http;
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
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function setupDeployWithHealthCheckMocks(): void
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

        InitiateDeploymentRequest::class => MockResponse::make([
            'data' => [
                'id' => 'deploy-123',
                'type' => 'deployments',
                'attributes' => [
                    'status' => 'pending',
                    'started_at' => now()->toISOString(),
                    'finished_at' => null,
                ],
            ],
        ], 200),

        GetDeploymentRequest::class => MockResponse::make([
            'data' => [
                'id' => 'deploy-123',
                'type' => 'deployments',
                'attributes' => [
                    'status' => 'deployment.succeeded',
                    'started_at' => now()->toISOString(),
                    'finished_at' => now()->toISOString(),
                ],
            ],
        ], 200),
    ]);
}

it('performs health check after successful deployment', function () {
    Prompt::fake();
    setupDeployWithHealthCheckMocks();

    Http::fake([
        'https://my-app.cloud.laravel.com/up' => Http::response('OK', 200),
    ]);

    $this->artisan('deploy', [
        'application' => 'My App',
        'environment' => 'production',
    ])->assertSuccessful();
});

it('warns when health check returns non-200 status', function () {
    Prompt::fake();
    setupDeployWithHealthCheckMocks();

    Http::fake([
        'https://my-app.cloud.laravel.com/up' => Http::response('Service Unavailable', 503),
    ]);

    $this->artisan('deploy', [
        'application' => 'My App',
        'environment' => 'production',
    ])->assertSuccessful();
});

it('handles health check connection failure gracefully', function () {
    Prompt::fake();
    setupDeployWithHealthCheckMocks();

    Http::fake([
        'https://my-app.cloud.laravel.com/up' => Http::response('', 500),
    ]);

    $this->artisan('deploy', [
        'application' => 'My App',
        'environment' => 'production',
    ])->assertSuccessful();
});
