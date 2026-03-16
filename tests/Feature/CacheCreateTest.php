<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Caches\CreateCacheRequest;
use App\Client\Resources\Caches\ListCacheTypesRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
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

function cacheCreateTypesResponse(): array
{
    return [
        'data' => [
            [
                'type' => 'laravel_valkey',
                'label' => 'Laravel Valkey',
                'regions' => ['us-east-1', 'us-east-2'],
                'sizes' => [
                    ['value' => 'cache-512mb', 'label' => '512 MB'],
                    ['value' => 'cache-1gb', 'label' => '1 GB'],
                ],
                'supports_auto_upgrade' => true,
            ],
        ],
    ];
}

function cacheCreateRegionsResponse(): array
{
    return [
        'data' => [
            ['region' => 'us-east-1', 'label' => 'US East 1', 'flag' => 'us'],
            ['region' => 'us-east-2', 'label' => 'US East 2', 'flag' => 'us'],
        ],
        'included' => [],
    ];
}

function cacheCreateCacheResponse(): array
{
    return [
        'data' => [
            'id' => 'cache-1',
            'type' => 'caches',
            'attributes' => [
                'name' => 'my-cache',
                'type' => 'laravel_valkey',
                'status' => 'creating',
                'region' => 'us-east-1',
                'size' => 'cache-512mb',
                'auto_upgrade_enabled' => false,
                'is_public' => false,
                'created_at' => now()->toISOString(),
                'connection' => [],
            ],
        ],
    ];
}

it('creates a cache with non-interactive options', function () {
    Prompt::fake();

    MockClient::global([
        ListCacheTypesRequest::class => MockResponse::make(cacheCreateTypesResponse(), 200),
        ListRegionsRequest::class => MockResponse::make(cacheCreateRegionsResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        CreateCacheRequest::class => MockResponse::make(cacheCreateCacheResponse(), 200),
    ]);

    $this->artisan('cache:create', [
        '--name' => 'my-cache',
        '--type' => 'laravel_valkey',
        '--region' => 'us-east-1',
        '--size' => 'cache-512mb',
        '--auto-upgrade-enabled' => 'false',
        '--is-public' => 'false',
        '--eviction-policy' => 'allkeys-lru',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('creates a cache with JSON output', function () {
    MockClient::global([
        ListCacheTypesRequest::class => MockResponse::make(cacheCreateTypesResponse(), 200),
        ListRegionsRequest::class => MockResponse::make(cacheCreateRegionsResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        CreateCacheRequest::class => MockResponse::make(cacheCreateCacheResponse(), 200),
    ]);

    $this->artisan('cache:create', [
        '--name' => 'my-cache',
        '--type' => 'laravel_valkey',
        '--region' => 'us-east-1',
        '--size' => 'cache-512mb',
        '--auto-upgrade-enabled' => 'false',
        '--is-public' => 'false',
        '--eviction-policy' => 'allkeys-lru',
        '--json' => true,
    ])->assertSuccessful();
});
