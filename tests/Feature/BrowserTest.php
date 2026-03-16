<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Sleep::fake();
    Process::fake();

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

afterEach(fn () => MockClient::destroyGlobal());

it('opens the environment URL in the browser', function () {
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
        ], 200),
    ]);

    $this->artisan('browser', [
        'application' => 'My App',
        'environment' => 'production',
    ])->assertSuccessful();

    Process::assertRan('open https://my-app.cloud.laravel.com');
});

it('returns failure when environment has no URL', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [createApplicationResponse()],
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org']],
                createEnvironmentResponse(['attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => '', 'status' => 'running', 'php_major_version' => '8.3']]),
            ],
            'links' => ['next' => null],
        ], 200),
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [createEnvironmentResponse(['attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => '', 'status' => 'running', 'php_major_version' => '8.3']])],
            'links' => ['next' => null],
        ], 200),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => createEnvironmentResponse(['attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => '', 'status' => 'running', 'php_major_version' => '8.3']]),
        ], 200),
    ]);

    $this->artisan('browser', [
        'application' => 'My App',
        'environment' => 'production',
    ])->assertFailed();

    Process::assertNothingRan();
});

it('fails when no GitHub remote is found in non-interactive mode', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(false);
    $this->mockGit->shouldReceive('ghInstalled')->andReturn(false)->byDefault();
    $this->mockGit->shouldReceive('ghAuthenticated')->andReturn(false)->byDefault();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
    ]);

    $this->artisan('browser', ['--no-interaction' => true])
        ->assertFailed();
});
