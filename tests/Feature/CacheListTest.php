<?php

use App\Client\Resources\Caches\ListCachesRequest;
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

function cacheListOrgResponse(): array
{
    return [
        'data' => [
            'id' => 'org-1',
            'type' => 'organizations',
            'attributes' => ['name' => 'My Org', 'slug' => 'my-org'],
        ],
    ];
}

function cacheListItemResponse(array $overrides = []): array
{
    return array_merge([
        'id' => 'cache-1',
        'type' => 'caches',
        'attributes' => [
            'name' => 'my-cache',
            'type' => 'laravel_valkey',
            'status' => 'running',
            'region' => 'us-east-1',
            'size' => 'cache-512mb',
            'auto_upgrade_enabled' => false,
            'is_public' => false,
            'created_at' => now()->toISOString(),
            'connection' => [],
        ],
    ], $overrides);
}

it('lists caches successfully', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(cacheListOrgResponse(), 200),
        ListCachesRequest::class => MockResponse::make([
            'data' => [cacheListItemResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('cache:list')
        ->assertSuccessful();
});

it('outputs empty JSON when no caches found in non-interactive mode', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(cacheListOrgResponse(), 200),
        ListCachesRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    // Empty list now returns failure
    $this->artisan('cache:list', ['--json' => true])
        ->assertFailed();
});

it('lists caches with JSON output', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(cacheListOrgResponse(), 200),
        ListCachesRequest::class => MockResponse::make([
            'data' => [cacheListItemResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('cache:list', ['--json' => true])
        ->assertSuccessful();
});

it('lists multiple caches', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(cacheListOrgResponse(), 200),
        ListCachesRequest::class => MockResponse::make([
            'data' => [
                cacheListItemResponse(),
                cacheListItemResponse([
                    'id' => 'cache-2',
                    'attributes' => [
                        'name' => 'second-cache',
                        'type' => 'laravel_valkey',
                        'status' => 'running',
                        'region' => 'us-east-2',
                        'size' => 'cache-1gb',
                        'auto_upgrade_enabled' => true,
                        'is_public' => false,
                        'created_at' => now()->toISOString(),
                        'connection' => [],
                    ],
                ]),
            ],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('cache:list')
        ->assertSuccessful();
});
