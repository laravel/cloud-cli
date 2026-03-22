<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
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
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('')->byDefault();
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(false)->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(fn () => MockClient::destroyGlobal());

function makeEnvListData(string $id, string $name): array
{
    return [
        'id' => $id,
        'type' => 'environments',
        'attributes' => [
            'name' => $name,
            'slug' => $name,
            'vanity_domain' => "my-app-{$name}.cloud.laravel.com",
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
}

function setupListEnvMocks(?array $environments = null): void
{
    $appData = createApplicationResponse();
    $orgInclude = ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']];

    $environments = $environments ?? [makeEnvListData('env-1', 'production')];

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
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => $environments,
            'links' => ['next' => null],
        ], 200),
    ]);
}

it('lists environments for an application', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupListEnvMocks();

    $this->artisan('environment:list', [
        'application' => 'app-123',
        '--no-interaction' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('production');
});

it('lists environments by application name', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupListEnvMocks();

    $this->artisan('environment:list', [
        'application' => 'My App',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('outputs json when --json flag is passed', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupListEnvMocks();

    $this->artisan('environment:list', [
        'application' => 'app-123',
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('production');
});

it('returns empty json when no environments found with --json', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupListEnvMocks([]);

    // Empty list now returns failure
    $this->artisan('environment:list', [
        'application' => 'app-123',
        '--json' => true,
    ])->assertFailed();
});

it('lists multiple environments', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupListEnvMocks([
        makeEnvListData('env-1', 'production'),
        makeEnvListData('env-2', 'staging'),
    ]);

    $this->artisan('environment:list', [
        'application' => 'app-123',
        '--no-interaction' => true,
    ])->assertSuccessful();
});
