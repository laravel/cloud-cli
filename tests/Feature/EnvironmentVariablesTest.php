<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Environments\AddEnvironmentVariablesRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\Client\Resources\Environments\ReplaceEnvironmentVariablesRequest;
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

function setupEnvVariablesMocks(): void
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
            'environment_variables' => [
                ['key' => 'APP_KEY', 'value' => 'base64:abc123'],
            ],
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
        AddEnvironmentVariablesRequest::class => MockResponse::make([], 200),
        ReplaceEnvironmentVariablesRequest::class => MockResponse::make([], 200),
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [createEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);
}

it('appends environment variables in non-interactive mode', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupEnvVariablesMocks();

    $this->artisan('environment:variables', [
        'environment' => 'env-1',
        '--action' => 'append',
        '--key' => 'NEW_VAR',
        '--value' => 'new_value',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('sets environment variables in non-interactive mode', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupEnvVariablesMocks();

    $this->artisan('environment:variables', [
        'environment' => 'env-1',
        '--action' => 'set',
        '--key' => 'APP_KEY',
        '--value' => 'new_key_value',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('replaces environment variables with --force in non-interactive mode', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupEnvVariablesMocks();

    $this->artisan('environment:variables', [
        'environment' => 'env-1',
        '--action' => 'replace',
        '--key' => 'APP_KEY',
        '--value' => 'new_value',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fails replace without --force in non-interactive mode', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupEnvVariablesMocks();

    $this->artisan('environment:variables', [
        'environment' => 'env-1',
        '--action' => 'replace',
        '--key' => 'APP_KEY',
        '--value' => 'new_value',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('fails with invalid action', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupEnvVariablesMocks();

    $this->artisan('environment:variables', [
        'environment' => 'env-1',
        '--action' => 'invalid',
        '--key' => 'APP_KEY',
        '--value' => 'new_value',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('outputs json when --json flag is passed for append', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupEnvVariablesMocks();

    $this->artisan('environment:variables', [
        'environment' => 'env-1',
        '--action' => 'append',
        '--key' => 'NEW_VAR',
        '--value' => 'new_value',
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('Environment variables updated');
});
