<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
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
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('')->byDefault();
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(false)->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(fn () => MockClient::destroyGlobal());

function envGetDetailData(string $id = 'env-1', string $name = 'production'): array
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
        'relationships' => [
            'application' => ['data' => ['id' => 'app-123', 'type' => 'applications']],
        ],
    ];
}

function setupGetEnvMocks(): void
{
    $appData = createApplicationResponse();
    $orgInclude = ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']];
    $envData = envGetDetailData();

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
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [createEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);
}

it('gets environment details by ID', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupGetEnvMocks();

    $this->artisan('environment:get', [
        'environment' => 'env-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('gets environment details with --json flag', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupGetEnvMocks();

    $this->artisan('environment:get', [
        'environment' => 'env-1',
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('production');
});

it('gets environment details by name', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupGetEnvMocks();

    $this->artisan('environment:get', [
        'environment' => 'production',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fails when environment not found by name with multiple envs', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    // Create an app with 2 environments so fromInput won't auto-resolve
    $appData = createApplicationResponse([
        'relationships' => [
            'organization' => ['data' => ['id' => 'org-1', 'type' => 'organizations']],
            'environments' => ['data' => [
                ['id' => 'env-1', 'type' => 'environments'],
                ['id' => 'env-2', 'type' => 'environments'],
            ]],
            'defaultEnvironment' => ['data' => ['id' => 'env-1', 'type' => 'environments']],
        ],
    ]);
    $orgInclude = ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']];
    $env1 = createEnvironmentResponse();
    $env2 = createEnvironmentResponse(['id' => 'env-2', 'attributes' => [
        'name' => 'staging',
        'slug' => 'staging',
        'vanity_domain' => 'my-app-staging.cloud.laravel.com',
        'status' => 'running',
        'php_major_version' => '8.3',
    ]]);

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [$appData],
            'included' => [$orgInclude, $env1, $env2],
            'links' => ['next' => null],
        ], 200),
        GetApplicationRequest::class => MockResponse::make([
            'data' => $appData,
            'included' => [$orgInclude, $env1, $env2],
        ], 200),
        GetEnvironmentRequest::class => MockResponse::make(['message' => 'Not found'], 404),
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [$env1, $env2],
            'links' => ['next' => null],
        ], 200),
    ]);

    // "nonexistent" doesn't match any env name, and with 2 envs fromInput needs interaction
    $this->artisan('environment:get', [
        'environment' => 'nonexistent',
        '--no-interaction' => true,
    ])->assertFailed();
});
