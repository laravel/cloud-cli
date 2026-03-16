<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Domains\CreateDomainRequest;
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

function createDomainResponseData(string $id = 'domain-1', string $name = 'example.com'): array
{
    return [
        'id' => $id,
        'type' => 'domains',
        'attributes' => [
            'name' => $name,
            'type' => 'root',
            'hostname_status' => 'active',
            'ssl_status' => 'active',
            'origin_status' => 'active',
            'redirect' => null,
            'dns_records' => [],
            'wildcard' => null,
            'www' => null,
            'last_verified_at' => null,
            'created_at' => null,
        ],
        'relationships' => [
            'environment' => ['data' => ['id' => 'env-1', 'type' => 'environments']],
        ],
    ];
}

function setupCreateDomainMocks(): void
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
        'relationships' => [
            'application' => ['data' => ['id' => 'app-123', 'type' => 'applications']],
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
            'included' => [
                array_merge($appData, [
                    'relationships' => array_merge($appData['relationships'], [
                        'organization' => ['data' => ['id' => 'org-1', 'type' => 'organizations']],
                    ]),
                ]),
                $orgInclude,
            ],
        ], 200),
        CreateDomainRequest::class => MockResponse::make([
            'data' => createDomainResponseData(),
        ], 200),
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [createEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);
}

it('creates a domain with all options', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupCreateDomainMocks();

    $this->artisan('domain:create', [
        'environment' => 'env-1',
        '--name' => 'example.com',
        '--www-redirect' => 'www_to_root',
        '--wildcard-enabled' => false,
        '--verification-method' => 'pre_verification',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fails without required --wildcard-enabled in non-interactive mode', function () {
    // BUG: DomainCreate does not provide a nonInteractively() default for wildcard_enabled
    // and verification_method, so they throw RuntimeException when not provided
    // in non-interactive mode (unlike www_redirect which has a nonInteractively default).
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupCreateDomainMocks();

    $this->artisan('domain:create', [
        'environment' => 'env-1',
        '--name' => 'example.com',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('outputs json when --json flag is passed', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupCreateDomainMocks();

    $this->artisan('domain:create', [
        'environment' => 'env-1',
        '--name' => 'example.com',
        '--www-redirect' => 'www_to_root',
        '--wildcard-enabled' => false,
        '--verification-method' => 'pre_verification',
        '--json' => true,
    ])->assertSuccessful()
      ->expectsOutputToContain('example.com');
});

it('creates a domain with root-to-www redirect', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupCreateDomainMocks();

    $this->artisan('domain:create', [
        'environment' => 'env-1',
        '--name' => 'example.com',
        '--www-redirect' => 'root_to_www',
        '--wildcard-enabled' => true,
        '--verification-method' => 'real_time',
        '--no-interaction' => true,
    ])->assertSuccessful();
});
