<?php

use App\Client\Resources\Caches\GetCacheMetricsRequest;
use App\Client\Resources\Caches\GetCacheRequest;
use App\Client\Resources\DatabaseClusters\GetDatabaseClusterMetricsRequest;
use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\Environments\GetEnvironmentMetricsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\WebSocketApplications\GetWebSocketApplicationMetricsRequest;
use App\Client\Resources\WebSocketApplications\GetWebSocketApplicationRequest;
use App\Client\Resources\WebSocketClusters\GetWebSocketClusterMetricsRequest;
use App\Client\Resources\WebSocketClusters\GetWebSocketClusterRequest;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
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

function metricsCommands(): array
{
    return [
        'database cluster' => [
            'command' => 'database-cluster:metrics',
            'argument' => ['cluster' => 'db-123'],
            'endpoint' => '/databases/clusters/db-123/metrics',
            'mocks' => fn (array $metrics) => [
                GetDatabaseClusterRequest::class => MockResponse::make(databaseClusterResponse(), 200),
                GetDatabaseClusterMetricsRequest::class => MockResponse::make(databaseClusterMetricsResponse($metrics), 200),
            ],
        ],
        'cache' => [
            'command' => 'cache:metrics',
            'argument' => ['cache' => 'cache-1'],
            'endpoint' => '/caches/cache-1/metrics',
            'mocks' => fn (array $metrics) => [
                GetCacheRequest::class => MockResponse::make(['data' => cacheResponse()], 200),
                GetCacheMetricsRequest::class => MockResponse::make(cacheMetricsResponse($metrics), 200),
            ],
        ],
        'environment' => [
            'command' => 'environment:metrics',
            'argument' => ['environment' => 'env-1'],
            'endpoint' => '/environments/env-1/metrics',
            'mocks' => fn (array $metrics) => [
                GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()], 200),
                GetEnvironmentMetricsRequest::class => MockResponse::make(environmentMetricsResponse($metrics), 200),
            ],
        ],
        'websocket application' => [
            'command' => 'websocket-application:metrics',
            'argument' => ['application' => 'wsa-1'],
            'endpoint' => '/websocket-applications/wsa-1/metrics',
            'mocks' => fn (array $metrics) => [
                GetWebSocketApplicationRequest::class => MockResponse::make(['data' => websocketApplicationResponse()], 200),
                GetWebSocketApplicationMetricsRequest::class => MockResponse::make(websocketMetricsResponse($metrics), 200),
            ],
        ],
        'websocket cluster' => [
            'command' => 'websocket-cluster:metrics',
            'argument' => ['cluster' => 'ws-1'],
            'endpoint' => '/websocket-servers/ws-1/metrics',
            'mocks' => fn (array $metrics) => [
                GetWebSocketClusterRequest::class => MockResponse::make(['data' => websocketClusterResponse(['id' => 'ws-1'])], 200),
                GetWebSocketClusterMetricsRequest::class => MockResponse::make(websocketMetricsResponse($metrics), 200),
            ],
        ],
    ];
}

function mockMetrics(string $name, array $metrics = []): MockClient
{
    return MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ...metricsCommands()[$name]['mocks']($metrics),
    ]);
}

function callMetrics(string $name, array $options = []): int
{
    $command = metricsCommands()[$name];

    return Artisan::call($command['command'], [
        ...$command['argument'],
        '--json' => true,
        '--no-interaction' => true,
        ...$options,
    ]);
}

dataset('metrics commands', array_keys(metricsCommands()));

it('requests the metrics endpoint for the last 24 hours by default', function (string $name) {
    $mockClient = mockMetrics($name);

    expect(callMetrics($name))->toBe(0);

    $request = $mockClient->getLastPendingRequest();

    expect($request->getUrl())->toEndWith(metricsCommands()[$name]['endpoint']);
    expect($request->query()->get('period'))->toBe('24h');
})->with('metrics commands');

it('sends the requested period', function (string $name) {
    $mockClient = mockMetrics($name);

    callMetrics($name, ['--period' => '7d']);

    expect($mockClient->getLastPendingRequest()->query()->get('period'))->toBe('7d');
})->with('metrics commands');

it('rejects a period the API does not support', function (string $name) {
    $mockClient = mockMetrics($name);

    expect(callMetrics($name, ['--period' => '12h']))->toBe(1);

    $mockClient->assertSentCount(0);
})->with('metrics commands');

it('includes the period metadata in JSON', function (string $name) {
    mockMetrics($name);

    callMetrics($name);

    expect(json_decode(Artisan::output(), true))->toMatchArray([
        'period' => '24h',
        'availablePeriods' => ['6h', '24h', '3d', '7d', '30d'],
    ]);
})->with('metrics commands');

it('outputs database cluster metrics as camelCase JSON', function () {
    mockMetrics('database cluster');

    callMetrics('database cluster');

    $decoded = json_decode(Artisan::output(), true);

    expect($decoded['cpuUsage'])->toMatchArray(['average' => 0.006, 'max' => 0.008]);
    expect($decoded['cpuUsage']['points'])->toHaveCount(2);
    expect($decoded['cpuUsage']['points'][0])->toMatchArray(['value' => 0.004]);
    expect($decoded['memoryUsage'])->toMatchArray([
        'current' => 270655488.0,
        'min' => 265416704.0,
        'max' => 275480576.0,
        'total' => null,
    ]);
    expect($decoded['computeHours']['points'])->toBe([]);
    expect($decoded['replicaLag'])->toBe([]);
});

it('reads replica lag series keyed by replica name', function () {
    mockMetrics('database cluster', [
        'data' => [
            'replica_lag' => [
                'replica-1' => [
                    'data' => [
                        ['x' => '2026-08-14T12:00:00.000000Z', 'y' => 0.5],
                        ['x' => '2026-08-14T12:02:00.000000Z', 'y' => 1.25],
                    ],
                    'average' => 0.875,
                ],
            ],
        ],
    ]);

    callMetrics('database cluster');

    $replicaLag = json_decode(Artisan::output(), true)['replicaLag'];

    expect($replicaLag)->toHaveCount(1);
    expect($replicaLag[0])->toMatchArray(['name' => 'replica-1', 'average' => 0.875]);
    expect($replicaLag[0]['points'])->toHaveCount(2);
});

it('splits labelled cache metrics into one series per label', function () {
    mockMetrics('cache');

    callMetrics('cache');

    $decoded = json_decode(Artisan::output(), true);

    expect(array_column($decoded['hitsAndMisses'], 'name'))->toBe(['Hits', 'Misses']);
    expect($decoded['hitsAndMisses'][1]['average'])->toEqual(2);
    expect(array_column($decoded['hitsAndMisses'][1]['points'], 'value'))->toEqual([1, 3]);
    expect($decoded['size']['total'])->toEqual(2097152);
});

it('treats an environment metric the API failed to collect as empty', function () {
    mockMetrics('environment');

    callMetrics('environment');

    $decoded = json_decode(Artisan::output(), true);

    expect($decoded['webWorkersCount'])->toBe([]);
    expect(array_column($decoded['cpuUsage'], 'name'))->toBe(['App', 'Worker']);
    expect($decoded['httpResponseCount'][0]['name'])->toBe('2xx');
    expect($decoded['httpResponseCount'][0]['total'])->toEqual(222);
});

it('filters JSON output to the requested fields', function () {
    mockMetrics('websocket cluster');

    callMetrics('websocket cluster', ['--fields' => 'period,connectionCount.current']);

    expect(json_decode(Artisan::output(), true))->toBe([
        'period' => '24h',
        'connectionCount' => ['current' => 30],
    ]);
});
