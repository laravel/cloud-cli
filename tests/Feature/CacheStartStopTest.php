<?php

use App\Client\Resources\Caches\GetCacheRequest;
use App\Client\Resources\Caches\StartCacheRequest;
use App\Client\Resources\Caches\StopCacheRequest;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $config = Mockery::mock(ConfigRepository::class);
    $config->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $config);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function setupCacheStartStopMocks(): void
{
    MockClient::global([
        GetCacheRequest::class => MockResponse::make(['data' => cacheResponse(['attributes' => ['name' => 'sessions']])], 200),
        StartCacheRequest::class => MockResponse::make(['data' => cacheResponse(['attributes' => ['name' => 'sessions', 'status' => 'available']])], 200),
        StopCacheRequest::class => MockResponse::make(['data' => cacheResponse(['attributes' => ['name' => 'sessions', 'status' => 'stopped']])], 200),
    ]);
}

it('starts a cache', function () {
    setupCacheStartStopMocks();

    $this->artisan('cache:start', ['cache' => 'cache-1', '--no-interaction' => true])
        ->assertSuccessful();

    MockClient::global()->assertSent(fn ($request) => $request instanceof StartCacheRequest
        && $request->getMethod()->value === 'POST'
        && $request->resolveEndpoint() === '/caches/cache-1/start');
    MockClient::global()->assertNotSent(StopCacheRequest::class);
});

it('outputs the started cache as json', function () {
    setupCacheStartStopMocks();

    Artisan::call('cache:start', ['cache' => 'cache-1', '--json' => true]);

    $output = json_decode(Artisan::output(), true);

    expect($output)->toMatchArray([
        'id' => 'cache-1',
        'name' => 'sessions',
        'status' => 'available',
    ]);
});

it('stops a cache with --force', function () {
    setupCacheStartStopMocks();

    $this->artisan('cache:stop', ['cache' => 'cache-1', '--force' => true, '--no-interaction' => true])
        ->assertSuccessful();

    MockClient::global()->assertSent(fn ($request) => $request instanceof StopCacheRequest
        && $request->getMethod()->value === 'POST'
        && $request->resolveEndpoint() === '/caches/cache-1/stop');
});

it('outputs the stopped cache as json', function () {
    setupCacheStartStopMocks();

    Artisan::call('cache:stop', ['cache' => 'cache-1', '--force' => true, '--json' => true]);

    $output = json_decode(Artisan::output(), true);

    expect($output)->toMatchArray([
        'id' => 'cache-1',
        'name' => 'sessions',
        'status' => 'stopped',
    ]);
});

it('requires --force to stop a cache non-interactively', function () {
    setupCacheStartStopMocks();

    $this->artisan('cache:stop', ['cache' => 'cache-1', '--no-interaction' => true])
        ->assertFailed();

    MockClient::global()->assertNotSent(StopCacheRequest::class);
});
