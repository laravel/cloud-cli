<?php

use App\Client\Resources\Domains\GetDomainRequest;
use App\Client\Resources\Domains\UpdateDomainRequest;
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

function domainUpdateData(): array
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

it('updates a domain with verification method using --force', function () {
    Prompt::fake();

    MockClient::global([
        GetDomainRequest::class => MockResponse::make([
            'data' => domainUpdateData(),
        ], 200),
        UpdateDomainRequest::class => MockResponse::make([
            'data' => domainUpdateData(),
        ], 200),
    ]);

    $this->artisan('domain:update', [
        'domain' => 'domain-123',
        '--verification-method' => 'pre_verification',
        '--force' => true,
    ])->assertSuccessful();
});

it('updates a domain with real_time verification method using --force', function () {
    Prompt::fake();

    MockClient::global([
        GetDomainRequest::class => MockResponse::make([
            'data' => domainUpdateData(),
        ], 200),
        UpdateDomainRequest::class => MockResponse::make([
            'data' => domainUpdateData(),
        ], 200),
    ]);

    $this->artisan('domain:update', [
        'domain' => 'domain-123',
        '--verification-method' => 'real_time',
        '--force' => true,
    ])->assertSuccessful();
});

it('outputs domain as JSON when --json flag is used', function () {
    Prompt::fake();

    MockClient::global([
        GetDomainRequest::class => MockResponse::make([
            'data' => domainUpdateData(),
        ], 200),
        UpdateDomainRequest::class => MockResponse::make([
            'data' => domainUpdateData(),
        ], 200),
    ]);

    $this->artisan('domain:update', [
        'domain' => 'domain-123',
        '--verification-method' => 'pre_verification',
        '--force' => true,
        '--json' => true,
    ])->assertSuccessful();
});

it('updates domain by name lookup', function () {
    Prompt::fake();

    MockClient::global([
        GetDomainRequest::class => MockResponse::make([
            'data' => domainUpdateData(),
        ], 200),
        UpdateDomainRequest::class => MockResponse::make([
            'data' => domainUpdateData(),
        ], 200),
    ]);

    $this->artisan('domain:update', [
        'domain' => 'domain-123',
        '--verification-method' => 'pre_verification',
        '--force' => true,
    ])->assertSuccessful();
});
