<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Environments\CreateEnvironmentRequest;
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

function envCreateNewEnvData(string $id = 'env-2', string $name = 'staging'): array
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

function setupCreateEnvMocks(string $envId = 'env-2', string $envName = 'staging'): void
{
    $appData = createApplicationResponse();
    $orgInclude = ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']];
    $envInclude = createEnvironmentResponse();
    $newEnvData = envCreateNewEnvData($envId, $envName);

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [$appData],
            'included' => [$orgInclude, $envInclude],
            'links' => ['next' => null],
        ], 200),
        GetApplicationRequest::class => MockResponse::make([
            'data' => $appData,
            'included' => [$orgInclude, $envInclude],
        ], 200),
        CreateEnvironmentRequest::class => MockResponse::make([
            'data' => $newEnvData,
        ], 200),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => array_merge($newEnvData, [
                'relationships' => [
                    'application' => ['data' => ['id' => 'app-123', 'type' => 'applications']],
                ],
            ]),
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
            'data' => [$envInclude],
            'links' => ['next' => null],
        ], 200),
    ]);
}

it('creates an environment with application ID and options', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupCreateEnvMocks();

    $this->artisan('environment:create', [
        'application' => 'app-123',
        '--name' => 'staging',
        '--branch' => 'develop',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('creates an environment with application name', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupCreateEnvMocks();

    $this->artisan('environment:create', [
        'application' => 'My App',
        '--name' => 'staging',
        '--branch' => 'develop',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('outputs json when --json flag is passed', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupCreateEnvMocks();

    $this->artisan('environment:create', [
        'application' => 'app-123',
        '--name' => 'staging',
        '--branch' => 'develop',
        '--json' => true,
    ])->assertSuccessful()
      ->expectsOutputToContain('staging');
});
