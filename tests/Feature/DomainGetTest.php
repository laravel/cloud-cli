<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Domains\GetDomainRequest;
use App\Client\Resources\Domains\ListDomainsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
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

function domainResponse(): array
{
    return [
        'id' => 'domain-123',
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
            'last_verified_at' => '2025-01-01T00:00:00.000000Z',
            'created_at' => '2025-01-01T00:00:00.000000Z',
        ],
        'relationships' => [
            'environment' => ['data' => ['id' => 'env-1', 'type' => 'environments']],
        ],
    ];
}

it('gets a domain by ID', function () {
    Prompt::fake();

    MockClient::global([
        GetDomainRequest::class => MockResponse::make([
            'data' => domainResponse(),
        ], 200),
    ]);

    $this->artisan('domain:get', ['domain' => 'domain-123'])
        ->assertSuccessful();
});

it('gets a domain by resolving from environment when no ID given', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [createApplicationResponse()],
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org']],
                createEnvironmentResponse(),
            ],
            'links' => ['next' => null],
        ], 200),
        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [createEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => createEnvironmentResponse(),
        ], 200),
        ListDomainsRequest::class => MockResponse::make([
            'data' => [domainResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('domain:get')
        ->assertSuccessful();
});
