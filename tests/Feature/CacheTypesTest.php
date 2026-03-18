<?php

use App\Client\Resources\Caches\ListCacheTypesRequest;
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

function cacheTypesResponse(): array
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

it('lists cache types successfully', function () {
    Prompt::fake();

    MockClient::global([
        ListCacheTypesRequest::class => MockResponse::make(cacheTypesResponse(), 200),
    ]);

    $this->artisan('cache:types')
        ->assertSuccessful();
});

it('lists cache types with JSON output', function () {
    MockClient::global([
        ListCacheTypesRequest::class => MockResponse::make(cacheTypesResponse(), 200),
    ]);

    $this->artisan('cache:types', ['--json' => true])
        ->assertSuccessful();
});

it('outputs empty JSON when no cache types found in non-interactive mode', function () {
    MockClient::global([
        ListCacheTypesRequest::class => MockResponse::make(['data' => []], 200),
    ]);

    // Empty list now returns failure
    $this->artisan('cache:types', ['--json' => true])
        ->assertFailed();
});

it('lists multiple cache types', function () {
    Prompt::fake();

    MockClient::global([
        ListCacheTypesRequest::class => MockResponse::make([
            'data' => [
                [
                    'type' => 'laravel_valkey',
                    'label' => 'Laravel Valkey',
                    'regions' => ['us-east-1'],
                    'sizes' => [
                        ['value' => 'cache-512mb', 'label' => '512 MB'],
                    ],
                    'supports_auto_upgrade' => true,
                ],
                [
                    'type' => 'laravel_redis',
                    'label' => 'Laravel Redis',
                    'regions' => ['us-east-1', 'eu-west-1'],
                    'sizes' => [
                        ['value' => 'cache-1gb', 'label' => '1 GB'],
                        ['value' => 'cache-2gb', 'label' => '2 GB'],
                    ],
                    'supports_auto_upgrade' => false,
                ],
            ],
        ], 200),
    ]);

    $this->artisan('cache:types')
        ->assertSuccessful();
});
