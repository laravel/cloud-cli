<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\StartDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\StopDatabaseClusterRequest;
use App\ConfigRepository;
use App\Prompts\Renderer;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Fixtures\InteractiveDatabaseClusterStartCommand;

beforeEach(function () {
    $config = Mockery::mock(ConfigRepository::class);
    $config->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $config);

    $this->suppressOutput = Renderer::$suppressOutput;
});

afterEach(function () {
    MockClient::destroyGlobal();

    Renderer::$suppressOutput = $this->suppressOutput;
});

function setupDatabaseClusterStartStopMocks(string $type = 'laravel_mysql'): void
{
    $cluster = fn (string $status) => databaseClusterResponse([
        'attributes' => ['name' => 'storefront-db', 'type' => $type, 'status' => $status],
    ]);

    MockClient::global([
        GetDatabaseClusterRequest::class => MockResponse::make($cluster('available'), 200),
        StartDatabaseClusterRequest::class => MockResponse::make($cluster('available'), 200),
        StopDatabaseClusterRequest::class => MockResponse::make($cluster('stopped'), 200),
    ]);
}

it('starts a database cluster', function () {
    setupDatabaseClusterStartStopMocks();

    $this->artisan('database-cluster:start', ['cluster' => 'db-123', '--no-interaction' => true])
        ->assertSuccessful();

    MockClient::global()->assertSent(fn ($request) => $request instanceof StartDatabaseClusterRequest
        && $request->getMethod()->value === 'POST'
        && $request->resolveEndpoint() === '/databases/clusters/db-123/start');
    MockClient::global()->assertNotSent(StopDatabaseClusterRequest::class);
});

it('outputs the started database cluster as json', function () {
    setupDatabaseClusterStartStopMocks();

    Artisan::call('database-cluster:start', ['cluster' => 'db-123', '--json' => true]);

    $output = json_decode(Artisan::output(), true);

    expect($output)->toMatchArray([
        'id' => 'db-123',
        'name' => 'storefront-db',
        'type' => 'laravel_mysql',
        'status' => 'available',
    ]);
    expect($output['schemas'])->toHaveCount(1);
});

it('tells a person to redeploy after starting a serverless postgres cluster', function () {
    Artisan::registerCommand(new InteractiveDatabaseClusterStartCommand);
    setupDatabaseClusterStartStopMocks('neon_serverless_postgres');

    expect(Artisan::call('test:interactive-database-cluster-start', ['cluster' => 'db-123']))->toBe(0);

    expect(Artisan::output())
        ->toContain("Database cluster 'storefront-db' started.")
        ->toContain('Redeploy connected environments');
});

it('does not ask for a redeploy after starting a mysql cluster', function () {
    Artisan::registerCommand(new InteractiveDatabaseClusterStartCommand);
    setupDatabaseClusterStartStopMocks('laravel_mysql');

    expect(Artisan::call('test:interactive-database-cluster-start', ['cluster' => 'db-123']))->toBe(0);

    expect(Artisan::output())
        ->toContain("Database cluster 'storefront-db' started.")
        ->not->toContain('Redeploy connected environments');
});

it('keeps the redeploy hint out of json output', function () {
    setupDatabaseClusterStartStopMocks('neon_serverless_postgres');

    Artisan::call('database-cluster:start', ['cluster' => 'db-123', '--json' => true]);

    $output = Artisan::output();

    expect($output)->not->toContain('Redeploy')
        ->and(json_decode($output, true))->toMatchArray(['type' => 'neon_serverless_postgres']);
});

it('stops a database cluster with --force', function () {
    setupDatabaseClusterStartStopMocks();

    $this->artisan('database-cluster:stop', ['cluster' => 'db-123', '--force' => true, '--no-interaction' => true])
        ->assertSuccessful();

    MockClient::global()->assertSent(fn ($request) => $request instanceof StopDatabaseClusterRequest
        && $request->getMethod()->value === 'POST'
        && $request->resolveEndpoint() === '/databases/clusters/db-123/stop');
});

it('outputs the stopped database cluster as json', function () {
    setupDatabaseClusterStartStopMocks();

    Artisan::call('database-cluster:stop', ['cluster' => 'db-123', '--force' => true, '--json' => true]);

    $output = json_decode(Artisan::output(), true);

    expect($output)->toMatchArray([
        'id' => 'db-123',
        'name' => 'storefront-db',
        'status' => 'stopped',
    ]);
});

it('requires --force to stop a database cluster non-interactively', function () {
    setupDatabaseClusterStartStopMocks();

    $this->artisan('database-cluster:stop', ['cluster' => 'db-123', '--no-interaction' => true])
        ->assertFailed();

    MockClient::global()->assertNotSent(StopDatabaseClusterRequest::class);
});
