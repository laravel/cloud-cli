<?php

use App\Client\Resources\Caches\DeleteCacheRequest;
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

function cacheDeleteGetResponse(): array
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

it('deletes a cache with force flag by ID', function () {
    Prompt::fake();

    MockClient::global([
        GetCacheRequest::class => MockResponse::make(cacheDeleteGetResponse(), 200),
        DeleteCacheRequest::class => MockResponse::make([], 200),
    ]);

    $this->artisan('cache:delete', [
        'cache' => 'cache-1',
        '--force' => true,
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
                        'auto_upgrade_enabled' => false,
                        'is_public' => false,
                        'created_at' => now()->toISOString(),
                        'connection' => [],
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),
        DeleteCacheRequest::class => MockResponse::make([], 200),
    ]);

    $this->artisan('cache:delete', [
        'cache' => 'my-cache',
        '--force' => true,
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
                        'auto_upgrade_enabled' => false,
                        'is_public' => false,
                        'created_at' => now()->toISOString(),
                        'connection' => [],
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),
        DeleteCacheRequest::class => MockResponse::make([], 200),
    ]);

    $this->artisan('cache:delete', [
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fails when no caches found', function () {
    Prompt::fake();

    MockClient::global([
        ListCachesRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('cache:delete', [
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();
});

it('deletes cache without force in non-interactive mode (uses default confirm=false)', function () {
    MockClient::global([
        GetCacheRequest::class => MockResponse::make(cacheDeleteGetResponse(), 200),
    ]);

    // Without --force in non-interactive mode, confirm() uses its default (false),
    // so the command returns FAILURE (cancelled)
    $this->artisan('cache:delete', [
        'cache' => 'cache-1',
        '--no-interaction' => true,
    ])->assertFailed();
});
