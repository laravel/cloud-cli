<?php

use App\Client\Resources\Applications\GetApplicationRequest;
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

function dashboardApplicationResponse(): array
{
    return [
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
            'environments' => ['data' => [['id' => 'env-1', 'type' => 'environments']]],
            'defaultEnvironment' => ['data' => ['id' => 'env-1', 'type' => 'environments']],
        ],
    ];
}

function dashboardEnvironmentWithAppResponse(): array
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

it('opens the dashboard URL in the browser', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [dashboardApplicationResponse()],
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
                createEnvironmentResponse(),
            ],
            'links' => ['next' => null],
        ], 200),
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [dashboardEnvironmentWithAppResponse()],
            'included' => [
                dashboardApplicationResponse(),
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
            ],
            'links' => ['next' => null],
        ], 200),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => dashboardEnvironmentWithAppResponse(),
            'included' => [
                dashboardApplicationResponse(),
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
            ],
        ], 200),
        GetApplicationRequest::class => MockResponse::make([
            'data' => dashboardApplicationResponse(),
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
                createEnvironmentResponse(),
            ],
        ], 200),
    ]);

    $this->artisan('dashboard', [
        'application' => 'My App',
    ])->assertSuccessful();

    Process::assertRan(function ($process) {
        return str_contains($process->command, 'open ');
    });
});

it('fails when no GitHub remote is found in non-interactive mode', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(false);
    $this->mockGit->shouldReceive('ghInstalled')->andReturn(false)->byDefault();
    $this->mockGit->shouldReceive('ghAuthenticated')->andReturn(false)->byDefault();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
    ]);

    $this->artisan('dashboard', ['--no-interaction' => true])
        ->assertFailed();
});
