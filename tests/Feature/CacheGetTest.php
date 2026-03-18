<?php

use App\Client\Resources\Caches\GetCacheRequest;
use App\Client\Resources\Caches\ListCachesRequest;
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

function cacheGetResponse(array $overrides = []): array
{
    return [
        'data' => array_merge([
            'id' => 'cache-1',
            'type' => 'caches',
            'attributes' => [
                'name' => 'my-cache',
                'type' => 'laravel_valkey',
                'status' => 'running',
                'region' => 'us-east-1',
                'size' => 'cache-512mb',
                'auto_upgrade_enabled' => true,
                'is_public' => false,
                'created_at' => now()->toISOString(),
                'connection' => [],
            ],
        ], $overrides),
    ];
}

it('gets cache details by ID', function () {
    Prompt::fake();

    MockClient::global([
        GetCacheRequest::class => MockResponse::make(cacheGetResponse(), 200),
    ]);

    $this->artisan('cache:get', [
        'cache' => 'cache-1',
    ])->assertSuccessful();
});

it('gets cache details with JSON output', function () {
    MockClient::global([
        GetCacheRequest::class => MockResponse::make(cacheGetResponse(), 200),
    ]);

    $this->artisan('cache:get', [
        'cache' => 'cache-1',
        '--json' => true,
    ])->assertSuccessful();
});

it('resolves cache by name', function () {
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
                        'auto_upgrade_enabled' => true,
                        'is_public' => false,
                        'created_at' => now()->toISOString(),
                        'connection' => [],
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('cache:get', [
        'cache' => 'my-cache',
    ])->assertSuccessful();
});

it('auto-selects sole cache when no argument given', function () {
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
                        'auto_upgrade_enabled' => true,
                        'is_public' => false,
                        'created_at' => now()->toISOString(),
                        'connection' => [],
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('cache:get', [
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fails when no caches found and no argument given', function () {
    Prompt::fake();

    MockClient::global([
        ListCachesRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('cache:get', [
        '--no-interaction' => true,
    ])->assertFailed();
});
