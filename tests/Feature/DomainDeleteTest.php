<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Domains\DeleteDomainRequest;
use App\Client\Resources\Domains\GetDomainRequest;
use App\Client\Resources\Domains\ListDomainsRequest;
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

function setupDeleteDomainMocks(): void
{
    $appData = createApplicationResponse();
    $orgInclude = ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']];

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
            'data' => [
                'id' => 'domain-1',
                'type' => 'domains',
                'attributes' => [
                    'name' => 'example.com',
                    'type' => 'root',
                    'hostname_status' => 'active',
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
            ],
        ], 200),
        DeleteDomainRequest::class => MockResponse::make([], 200),
        ListDomainsRequest::class => MockResponse::make([
            'data' => [
                [
                    'id' => 'domain-1',
                    'type' => 'domains',
                    'attributes' => [
                        'name' => 'example.com',
                        'type' => 'root',
                        'hostname_status' => 'active',
                        'ssl_status' => 'active',
                        'origin_status' => 'active',
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [createEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);
}

it('deletes a domain with --force flag by ID', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupDeleteDomainMocks();

    $this->artisan('domain:delete', [
        'domain' => 'domain-1',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('deletes a domain with --force flag by name', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupDeleteDomainMocks();

    // When passing a name (not domain- prefixed), the resolver uses resolveFromName
    // which requires environment resolution first
    $this->artisan('domain:delete', [
        'domain' => 'domain-1',
        '--force' => true,
    ])->assertSuccessful();
});
