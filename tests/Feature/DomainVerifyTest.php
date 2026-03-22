<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Domains\GetDomainRequest;
use App\Client\Resources\Domains\ListDomainsRequest;
use App\Client\Resources\Domains\VerifyDomainRequest;
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

function setupVerifyDomainMocks(string $hostnameStatus = 'active'): void
{
    $appData = createApplicationResponse();
    $orgInclude = ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']];
    $domainData = [
        'id' => 'domain-1',
        'type' => 'domains',
        'attributes' => [
            'name' => 'example.com',
            'type' => 'root',
            'hostname_status' => $hostnameStatus,
            'ssl_status' => 'active',
            'origin_status' => 'active',
            'redirect' => null,
            'dns_records' => [],
            'wildcard' => null,
            'www' => null,
        ],
        'relationships' => [
            'environment' => ['data' => ['id' => 'env-1', 'type' => 'environments']],
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
            'data' => [
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
            ],
        ], 200),
        GetDomainRequest::class => MockResponse::make([
            'data' => $domainData,
        ], 200),
        VerifyDomainRequest::class => MockResponse::make([
            'data' => $domainData,
        ], 200),
        ListDomainsRequest::class => MockResponse::make([
            'data' => [$domainData],
            'links' => ['next' => null],
        ], 200),
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [createEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);
}

it('verifies a domain by ID', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupVerifyDomainMocks();

    $this->artisan('domain:verify', [
        'domain' => 'domain-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('outputs json when --json flag is passed', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupVerifyDomainMocks();

    $this->artisan('domain:verify', [
        'domain' => 'domain-1',
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('example.com');
});

it('verifies a domain with pending status', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupVerifyDomainMocks('pending');

    $this->artisan('domain:verify', [
        'domain' => 'domain-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});
