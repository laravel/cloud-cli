<?php

use App\Client\Resources\WebSocketClusters\GetWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\StartWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\StopWebSocketClusterRequest;
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

function setupWebsocketClusterStartStopMocks(): void
{
    $cluster = fn (string $status) => ['data' => websocketClusterResponse([
        'id' => 'ws-1',
        'attributes' => ['name' => 'storefront-ws', 'status' => $status],
    ])];

    MockClient::global([
        GetWebSocketClusterRequest::class => MockResponse::make($cluster('available'), 200),
        StartWebSocketClusterRequest::class => MockResponse::make($cluster('available'), 200),
        StopWebSocketClusterRequest::class => MockResponse::make($cluster('stopped'), 200),
    ]);
}

it('starts a websocket cluster', function () {
    setupWebsocketClusterStartStopMocks();

    $this->artisan('websocket-cluster:start', ['cluster' => 'ws-1', '--no-interaction' => true])
        ->assertSuccessful();

    MockClient::global()->assertSent(fn ($request) => $request instanceof StartWebSocketClusterRequest
        && $request->getMethod()->value === 'POST'
        && $request->resolveEndpoint() === '/websocket-servers/ws-1/start');
    MockClient::global()->assertNotSent(StopWebSocketClusterRequest::class);
});

it('outputs the started websocket cluster as json', function () {
    setupWebsocketClusterStartStopMocks();

    Artisan::call('websocket-cluster:start', ['cluster' => 'ws-1', '--json' => true]);

    $output = json_decode(Artisan::output(), true);

    expect($output)->toMatchArray([
        'id' => 'ws-1',
        'name' => 'storefront-ws',
        'status' => 'available',
    ]);
});

it('stops a websocket cluster with --force', function () {
    setupWebsocketClusterStartStopMocks();

    $this->artisan('websocket-cluster:stop', ['cluster' => 'ws-1', '--force' => true, '--no-interaction' => true])
        ->assertSuccessful();

    MockClient::global()->assertSent(fn ($request) => $request instanceof StopWebSocketClusterRequest
        && $request->getMethod()->value === 'POST'
        && $request->resolveEndpoint() === '/websocket-servers/ws-1/stop');
});

it('outputs the stopped websocket cluster as json', function () {
    setupWebsocketClusterStartStopMocks();

    Artisan::call('websocket-cluster:stop', ['cluster' => 'ws-1', '--force' => true, '--json' => true]);

    $output = json_decode(Artisan::output(), true);

    expect($output)->toMatchArray([
        'id' => 'ws-1',
        'name' => 'storefront-ws',
        'status' => 'stopped',
    ]);
});

it('requires --force to stop a websocket cluster non-interactively', function () {
    setupWebsocketClusterStartStopMocks();

    $this->artisan('websocket-cluster:stop', ['cluster' => 'ws-1', '--no-interaction' => true])
        ->assertFailed();

    MockClient::global()->assertNotSent(StopWebSocketClusterRequest::class);
});
