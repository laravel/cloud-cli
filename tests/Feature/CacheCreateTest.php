<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Caches\CreateCacheRequest;
use App\Client\Resources\Caches\GetCacheRequest;
use App\Client\Resources\Caches\ListCacheTypesRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
use App\ConfigRepository;
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

function cacheTypesResponse(): array
{
    return [
        'data' => [
            [
                'type' => 'laravel_valkey',
                'label' => 'Laravel Valkey',
                'regions' => ['us-east-1'],
                'sizes' => [
                    ['value' => 'valkey-flex-250mb', 'label' => '250 MB', 'supports_hibernation' => true],
                    ['value' => 'valkey-pro.1gb', 'label' => '1 GB', 'supports_hibernation' => false],
                ],
                'supports_auto_upgrade' => true,
                'supports_eviction_policy' => true,
                'supports_hibernation' => true,
            ],
            [
                'type' => 'aws_elasticache_valkey',
                'label' => 'ElastiCache Valkey',
                'regions' => ['us-east-1'],
                'sizes' => [
                    ['value' => 'elasticache.cache.t4g.micro', 'label' => 'Micro', 'supports_hibernation' => false],
                ],
                'supports_auto_upgrade' => false,
                'supports_eviction_policy' => false,
                'supports_hibernation' => false,
            ],
        ],
    ];
}

function setupCacheCreateMocks(): Closure
{
    $sentBody = new stdClass;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make(['data' => []], 200),
        ListCacheTypesRequest::class => MockResponse::make(cacheTypesResponse(), 200),
        ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
        CreateCacheRequest::class => function (PendingRequest $request) use ($sentBody) {
            $sentBody->value = $request->body()->all();

            return MockResponse::make(['data' => cacheResponse()], 201);
        },
    ]);

    return fn () => $sentBody->value ?? null;
}

function cacheCreateArgs(array $overrides = []): array
{
    return array_merge([
        '--name' => 'my-cache',
        '--type' => 'laravel_valkey',
        '--region' => 'us-east-1',
        '--size' => 'valkey-flex-250mb',
        '--auto-upgrade-enabled' => 'false',
        '--is-public' => 'false',
        '--no-interaction' => true,
    ], $overrides);
}

it('defaults the eviction policy when the type supports one', function () {
    $sentBody = setupCacheCreateMocks();

    $this->artisan('cache:create', cacheCreateArgs())->assertSuccessful();

    expect($sentBody())->toMatchArray([
        'eviction_policy' => 'allkeys-lru',
        'uses_hibernation' => false,
    ])->not->toHaveKey('hibernation_timeout');
});

it('sends the requested eviction policy', function () {
    $sentBody = setupCacheCreateMocks();

    $this->artisan('cache:create', cacheCreateArgs(['--eviction-policy' => 'volatile-ttl']))->assertSuccessful();

    expect($sentBody()['eviction_policy'])->toBe('volatile-ttl');
});

it('sends hibernation settings', function () {
    $sentBody = setupCacheCreateMocks();

    $this->artisan('cache:create', cacheCreateArgs([
        '--uses-hibernation' => 'true',
        '--hibernation-timeout' => '15',
    ]))->assertSuccessful();

    expect($sentBody())->toMatchArray([
        'uses_hibernation' => true,
        'hibernation_timeout' => 15,
    ]);
});

it('requires a timeout when hibernation is enabled', function () {
    setupCacheCreateMocks();

    $this->artisan('cache:create', cacheCreateArgs(['--uses-hibernation' => 'true']))->assertFailed();

    MockClient::global()->assertNotSent(CreateCacheRequest::class);
});

it('leaves out hibernation when the size does not support it', function () {
    $sentBody = setupCacheCreateMocks();

    $this->artisan('cache:create', cacheCreateArgs(['--size' => 'valkey-pro.1gb']))->assertSuccessful();

    expect($sentBody())->not->toHaveKey('uses_hibernation');
});

it('sends the dedicated cluster and no eviction policy for elasticache', function () {
    $sentBody = setupCacheCreateMocks();

    $this->artisan('cache:create', cacheCreateArgs([
        '--type' => 'aws_elasticache_valkey',
        '--size' => 'elasticache.cache.t4g.micro',
        '--auto-upgrade-enabled' => null,
        '--cluster-id' => 'cluster-1',
    ]))->assertSuccessful();

    expect($sentBody())->toMatchArray([
        'cluster_id' => 'cluster-1',
        'eviction_policy' => null,
        'auto_upgrade_enabled' => false,
    ])->not->toHaveKey('uses_hibernation');
});

it('reads eviction policy and hibernation from the cache', function () {
    MockClient::global([
        GetCacheRequest::class => MockResponse::make(['data' => cacheResponse(['attributes' => [
            'eviction_policy' => 'volatile-lru',
            'uses_hibernation' => true,
            'hibernation_timeout' => 10,
        ]])], 200),
    ]);

    $this->artisan('cache:get', ['cache' => 'cache-1', '--json' => true])
        ->expectsOutputToContain('"evictionPolicy":"volatile-lru","usesHibernation":true,"hibernationTimeout":10')
        ->assertSuccessful();
});
