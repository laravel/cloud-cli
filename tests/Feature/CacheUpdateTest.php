<?php

use App\Client\Connector;
use App\Client\Resources\Caches\GetCacheRequest;
use App\Client\Resources\Caches\ListCacheTypesRequest;
use App\Client\Resources\Caches\UpdateCacheRequest;
use App\Commands\CacheUpdate;
use App\ConfigRepository;
use App\Dto\Cache;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function setupCacheUpdateMocks(): Closure
{
    $sentBody = new stdClass;

    MockClient::global([
        GetCacheRequest::class => MockResponse::make(['data' => cacheResponse()], 200),
        ListCacheTypesRequest::class => MockResponse::make([
            'data' => [
                [
                    'type' => 'laravel_valkey',
                    'label' => 'Laravel Valkey',
                    'regions' => ['us-east-1'],
                    'sizes' => [
                        ['value' => '250mb', 'label' => '250 MB', 'supports_hibernation' => true],
                        ['value' => '1gb', 'label' => '1 GB', 'supports_hibernation' => false],
                    ],
                    'supports_auto_upgrade' => true,
                    'supports_eviction_policy' => true,
                    'supports_hibernation' => true,
                ],
                [
                    'type' => 'upstash_redis',
                    'label' => 'Upstash Redis',
                    'regions' => ['us-east-1'],
                    'sizes' => [
                        ['value' => '256mb', 'label' => '256 MB'],
                        ['value' => '3gb', 'label' => '3 GB'],
                    ],
                    'supports_auto_upgrade' => false,
                ],
            ],
        ], 200),
        UpdateCacheRequest::class => function (PendingRequest $request) use ($sentBody) {
            $sentBody->value = $request->body()->all();

            return MockResponse::make(['data' => cacheResponse()], 200);
        },
    ]);

    return fn () => $sentBody->value ?? null;
}

function invokeCacheUpdate(string $method, array $cacheAttributes): array
{
    $command = app(CacheUpdate::class);
    (new ReflectionProperty($command, 'client'))->setValue($command, new Connector('test-api-token'));

    $cache = Cache::createFromResponse(['data' => cacheResponse(['attributes' => $cacheAttributes])]);

    return (new ReflectionMethod($command, $method))->invoke($command, $cache);
}

function cacheSizeOptions(array $cacheAttributes): array
{
    return invokeCacheUpdate('sizeOptions', $cacheAttributes);
}

it('offers the sizes for the cache type from the api', function () {
    setupCacheUpdateMocks();

    expect(cacheSizeOptions(['type' => 'upstash_redis']))->toBe(['256mb' => '256 MB', '3gb' => '3 GB']);
});

it('sends the size option without fetching cache types', function () {
    $sentBody = setupCacheUpdateMocks();

    $this->artisan('cache:update', ['cache' => 'cache-1', '--size' => '1gb', '--no-interaction' => true])
        ->assertSuccessful();

    expect($sentBody())->toMatchArray(['size' => '1gb']);
    MockClient::global()->assertNotSent(ListCacheTypesRequest::class);
});

it('sends the eviction policy option', function () {
    $sentBody = setupCacheUpdateMocks();

    $this->artisan('cache:update', ['cache' => 'cache-1', '--eviction-policy' => 'noeviction', '--no-interaction' => true])
        ->assertSuccessful();

    expect($sentBody())->toBe(['eviction_policy' => 'noeviction']);
});

it('sends the hibernation options', function () {
    $sentBody = setupCacheUpdateMocks();

    $this->artisan('cache:update', [
        'cache' => 'cache-1',
        '--uses-hibernation' => 'true',
        '--hibernation-timeout' => '30',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->toBe(['uses_hibernation' => true, 'hibernation_timeout' => 30]);
});

it('offers every field when the cache type and size support them', function () {
    setupCacheUpdateMocks();

    expect(invokeCacheUpdate('unsupportedFields', ['type' => 'laravel_valkey', 'size' => '250mb']))->toBe([]);
});

it('hides hibernation for a size that cannot hibernate', function () {
    setupCacheUpdateMocks();

    expect(invokeCacheUpdate('unsupportedFields', ['type' => 'laravel_valkey', 'size' => '1gb']))
        ->toBe(['uses_hibernation', 'hibernation_timeout']);
});

it('hides eviction policy and hibernation for a type without them', function () {
    setupCacheUpdateMocks();

    expect(invokeCacheUpdate('unsupportedFields', ['type' => 'upstash_redis', 'size' => '256mb']))
        ->toBe(['eviction_policy', 'uses_hibernation', 'hibernation_timeout']);
});
