<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\Client\Resources\Environments\UpdateEnvironmentRequest;
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

function envUpdateData(string $id = 'env-1', string $name = 'production', array $attrOverrides = []): array
{
    return [
        'id' => $id,
        'type' => 'environments',
        'attributes' => array_merge([
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
        ], $attrOverrides),
    ];
}

function setupUpdateEnvMocks(array $updatedAttrs = []): void
{
    $appData = createApplicationResponse();
    $orgInclude = ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']];
    $envData = envUpdateData();
    $updatedEnvData = envUpdateData('env-1', 'production', $updatedAttrs);

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
            'included' => [
                array_merge($appData, [
                    'relationships' => array_merge($appData['relationships'], [
                        'organization' => ['data' => ['id' => 'org-1', 'type' => 'organizations']],
                    ]),
                ]),
                $orgInclude,
            ],
        ], 200),
        UpdateEnvironmentRequest::class => MockResponse::make([
            'data' => $updatedEnvData,
        ], 200),
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [createEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);
}

it('updates an environment branch with --force flag', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupUpdateEnvMocks(['branch' => 'develop']);

    $this->artisan('environment:update', [
        'environment' => 'env-1',
        '--branch' => 'develop',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('updates environment build command', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupUpdateEnvMocks(['build_command' => 'npm run build']);

    $this->artisan('environment:update', [
        'environment' => 'env-1',
        '--build-command' => 'npm run build',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('updates environment deploy command', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupUpdateEnvMocks(['deploy_command' => 'php artisan migrate']);

    $this->artisan('environment:update', [
        'environment' => 'env-1',
        '--deploy-command' => 'php artisan migrate',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fails when no fields are provided in non-interactive mode', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupUpdateEnvMocks();

    $this->artisan('environment:update', [
        'environment' => 'env-1',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('outputs json when --json flag is passed', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupUpdateEnvMocks(['branch' => 'develop']);

    $this->artisan('environment:update', [
        'environment' => 'env-1',
        '--branch' => 'develop',
        '--force' => true,
        '--json' => true,
    ])->assertSuccessful()
      ->expectsOutputToContain('production');
});

it('updates multiple fields at once', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupUpdateEnvMocks(['branch' => 'develop', 'build_command' => 'npm run build', 'deploy_command' => 'php artisan migrate']);

    $this->artisan('environment:update', [
        'environment' => 'env-1',
        '--branch' => 'develop',
        '--build-command' => 'npm run build',
        '--deploy-command' => 'php artisan migrate',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});
