<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\Client\Resources\Environments\StopEnvironmentRequest;
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

function setupStopEnvMocks(): void
{
    $appData = createApplicationResponse();
    $orgInclude = ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']];
    $envData = [
        'id' => 'env-1',
        'type' => 'environments',
        'attributes' => [
            'name' => 'production',
            'slug' => 'production',
            'vanity_domain' => 'my-app.cloud.laravel.com',
            'status' => 'running',
            'php_major_version' => '8.3',
            'build_command' => null,
            'deploy_command' => null,
            'created_from_automation' => false,
            'uses_octane' => false,
            'uses_hibernation' => false,
            'uses_push_to_deploy' => false,
            'uses_deploy_hook' => false,
            'environment_variables' => [],
            'network_settings' => [],
        ],
    ];

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [$appData],
            'included' => [$orgInclude, createEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),
        GetApplicationRequest::class => MockResponse::make([
            'data' => $appData,
            'included' => [$orgInclude, createEnvironmentResponse()],
        ], 200),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => $envData,
        ], 200),
        StopEnvironmentRequest::class => MockResponse::make([], 200),
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [createEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);
}

it('stops an environment with --force flag', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupStopEnvMocks();

    $this->artisan('environment:stop', [
        'environment' => 'env-1',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('stops an environment by name with --force', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupStopEnvMocks();

    $this->artisan('environment:stop', [
        'environment' => 'production',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('cancels without --force in non-interactive mode', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupStopEnvMocks();

    $this->artisan('environment:stop', [
        'environment' => 'env-1',
        '--force' => true,
    ])->assertSuccessful();
});
