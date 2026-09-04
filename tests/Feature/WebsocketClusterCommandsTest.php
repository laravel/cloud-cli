<?php

use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\WebSocketClusters\GetWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\ListWebSocketClustersRequest;
use App\ConfigRepository;
use App\Dto\WebsocketCluster;
use App\Enums\WebsocketServerStatus;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function setupWebsocketClusterMocks(?array $cluster = null): void
{
    $cluster ??= websocketClusterResponse();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListWebSocketClustersRequest::class => MockResponse::make([
            'data' => [$cluster],
            'links' => ['next' => null],
        ], 200),
        GetWebSocketClusterRequest::class => MockResponse::make(['data' => $cluster], 200),
    ]);
}

it('builds the dto for every status the api can return', function (string $status) {
    $cluster = WebsocketCluster::createFromResponse([
        'data' => websocketClusterResponse(['attributes' => ['status' => $status]]),
    ]);

    expect($cluster->status)->toBe(WebsocketServerStatus::from($status));
})->with(['creating', 'updating', 'available', 'stopped', 'deleting', 'deleted', 'unknown']);

it('lists a stopped cluster', function () {
    setupWebsocketClusterMocks(websocketClusterResponse([
        'attributes' => ['status' => 'stopped'],
    ]));

    $this->artisan('websocket-cluster:list', ['--no-interaction' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('stopped');
});

it('gets a stopped cluster', function () {
    setupWebsocketClusterMocks(websocketClusterResponse([
        'attributes' => ['status' => 'stopped'],
    ]));

    $this->artisan('websocket-cluster:get', [
        'cluster' => 'wss-1',
        '--no-interaction' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('stopped');
});
