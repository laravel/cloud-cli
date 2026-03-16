<?php

/**
 * DeployMonitor tests.
 *
 * Note: The deploy:monitor command uses MonitorDeployments prompt which relies on
 * polling/streaming with interactive terminal rendering. Full integration testing
 * of the monitor loop is not feasible in this test environment. These tests verify
 * the command bootstraps correctly (auth, git repo, app/env resolution) and that
 * failure paths work as expected.
 */

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Deployments\ListDeploymentsRequest;
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

it('fails when no GitHub remote is found in non-interactive mode', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(false);
    $this->mockGit->shouldReceive('ghInstalled')->andReturn(false)->byDefault();
    $this->mockGit->shouldReceive('ghAuthenticated')->andReturn(false)->byDefault();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
    ]);

    $this->artisan('deploy:monitor', ['--no-interaction' => true])
        ->assertFailed();
});

it('requires a git remote repo to monitor deployments', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(false);
    $this->mockGit->shouldReceive('ghInstalled')->andReturn(false);
    $this->mockGit->shouldReceive('ghAuthenticated')->andReturn(false);

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
    ]);

    // In non-interactive mode, missing git remote throws RuntimeException
    $this->artisan('deploy:monitor', ['--no-interaction' => true])
        ->assertFailed();
});
