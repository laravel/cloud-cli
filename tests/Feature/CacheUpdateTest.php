<?php

use App\Client\Resources\Caches\GetCacheRequest;
use App\Client\Resources\Caches\ListCachesRequest;
use App\Client\Resources\Caches\UpdateCacheRequest;
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

function cacheUpdateGetResponse(): array
{
    return [
        'data' => [
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
        ],
    ];
}

function cacheUpdateUpdatedResponse(): array
{
    return [
        'data' => [
            'id' => 'cache-1',
            'type' => 'caches',
            'attributes' => [
                'name' => 'updated-cache',
                'type' => 'laravel_valkey',
                'status' => 'running',
                'region' => 'us-east-1',
                'size' => '1gb',
                'auto_upgrade_enabled' => true,
                'is_public' => true,
                'created_at' => now()->toISOString(),
                'connection' => [],
            ],
        ],
    ];
}

it('updates a cache with all options via flags', function () {
    Prompt::fake();

    $getCalls = 0;
    MockClient::global([
        GetCacheRequest::class => function () use (&$getCalls) {
            $getCalls++;

            return $getCalls === 1
                ? MockResponse::make(cacheUpdateGetResponse(), 200)
                : MockResponse::make(cacheUpdateUpdatedResponse(), 200);
        },
        UpdateCacheRequest::class => MockResponse::make(cacheUpdateUpdatedResponse(), 200),
    ]);

    $this->artisan('cache:update', [
        'cache' => 'cache-1',
        '--name' => 'updated-cache',
        '--size' => '1gb',
        '--auto-upgrade-enabled' => 'true',
        '--is-public' => 'true',
        '--force' => true,
    ])->assertSuccessful();
});

it('updates a cache with JSON output', function () {
    $getCalls = 0;
    MockClient::global([
        GetCacheRequest::class => function () use (&$getCalls) {
            $getCalls++;

            return $getCalls === 1
                ? MockResponse::make(cacheUpdateGetResponse(), 200)
                : MockResponse::make(cacheUpdateUpdatedResponse(), 200);
        },
        UpdateCacheRequest::class => MockResponse::make(cacheUpdateUpdatedResponse(), 200),
    ]);

    $this->artisan('cache:update', [
        'cache' => 'cache-1',
        '--name' => 'updated-cache',
        '--size' => '1gb',
        '--force' => true,
        '--json' => true,
    ])->assertSuccessful();
});

it('updates a cache resolved by name', function () {
    Prompt::fake();

    MockClient::global([
        ListCachesRequest::class => MockResponse::make([
            'data' => [
                [
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
                ],
            ],
            'links' => ['next' => null],
        ], 200),
        UpdateCacheRequest::class => MockResponse::make(cacheUpdateUpdatedResponse(), 200),
        GetCacheRequest::class => MockResponse::make(cacheUpdateUpdatedResponse(), 200),
    ]);

    $this->artisan('cache:update', [
        'cache' => 'my-cache',
        '--name' => 'updated-cache',
        '--force' => true,
    ])->assertSuccessful();
});
