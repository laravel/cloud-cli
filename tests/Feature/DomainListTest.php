<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
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

function makeDomainListItem(string $id, string $name, string $type = 'root'): array
{
    return [
        'id' => $id,
        'type' => 'domains',
        'attributes' => [
            'name' => $name,
            'type' => $type,
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
    ];
}

function setupListDomainMocks(?array $domains = null): void
{
    $appData = createApplicationResponse();
    $orgInclude = ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']];

    $domains = $domains ?? [makeDomainListItem('domain-1', 'example.com')];

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
        ListDomainsRequest::class => MockResponse::make([
            'data' => $domains,
            'links' => ['next' => null],
        ], 200),
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [createEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);
}

it('lists domains for an environment', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupListDomainMocks();

    $this->artisan('domain:list', [
        'environment' => 'env-1',
        '--no-interaction' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('example.com');
});

it('outputs json when --json flag is passed', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupListDomainMocks();

    $this->artisan('domain:list', [
        'environment' => 'env-1',
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('example.com');
});

it('returns empty json when no domains found with --json', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupListDomainMocks([]);

    // Empty list now returns failure
    $this->artisan('domain:list', [
        'environment' => 'env-1',
        '--json' => true,
    ])->assertFailed();
});

it('lists multiple domains', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupListDomainMocks([
        makeDomainListItem('domain-1', 'example.com'),
        makeDomainListItem('domain-2', 'api.example.com', 'subdomain'),
    ]);

    $this->artisan('domain:list', [
        'environment' => 'env-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});
