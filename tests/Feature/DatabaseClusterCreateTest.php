<?php

use App\Client\Resources\DatabaseClusters\CreateDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseTypesRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
use App\Commands\DatabaseClusterCreate;
use App\ConfigRepository;
use App\Dto\DatabaseCluster;
use App\Dto\DatabaseType;
use Illuminate\Support\Sleep;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    Sleep::fake();

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function setupDatabaseClusterCreateMocks(): Closure
{
    $sentBody = new stdClass;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListDatabaseTypesRequest::class => MockResponse::make(versionlessDatabaseTypesResponse(), 200),
        ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
        CreateDatabaseClusterRequest::class => function (PendingRequest $request) use ($sentBody) {
            $sentBody->value = $request->body()->all();

            return MockResponse::make(databaseClusterResponse([
                'attributes' => ['type' => $sentBody->value['type']],
            ]), 201);
        },
    ]);

    return fn () => $sentBody->value ?? null;
}

it('creates a cluster on the newest version when none is given', function () {
    $sentBody = setupDatabaseClusterCreateMocks();

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'neon_serverless_postgres',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody()['type'])->toBe('neon_serverless_postgres')
        ->and($sentBody()['version'])->toBe('18')
        ->and($sentBody()['config'])->toBe([
            'cu_min' => 0.25,
            'cu_max' => 0.25,
            'suspend_seconds' => 300,
            'retention_days' => 0,
        ]);
});

it('pins the cluster to the requested engine version', function () {
    $sentBody = setupDatabaseClusterCreateMocks();

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'neon_serverless_postgres',
        '--engine-version' => '17',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody()['type'])->toBe('neon_serverless_postgres')
        ->and($sentBody()['version'])->toBe('17');
});

it('sends the dotted version for MySQL', function () {
    $sentBody = setupDatabaseClusterCreateMocks();

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'laravel_mysql',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody()['type'])->toBe('laravel_mysql')
        ->and($sentBody()['version'])->toBe('8.4');
});

it('accepts a two character name', function () {
    $sentBody = setupDatabaseClusterCreateMocks();

    $this->artisan('database-cluster:create', [
        '--name' => 'db',
        '--type' => 'neon_serverless_postgres',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody()['name'])->toBe('db');
});

it('sends the dedicated cluster and default database options', function () {
    $sentBody = setupDatabaseClusterCreateMocks();

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'laravel_mysql',
        '--region' => 'us-east-1',
        '--cluster' => 'cluster-abc',
        '--create-default-database' => 'false',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody()['cluster_id'])->toBe('cluster-abc')
        ->and($sentBody()['create_default_database'])->toBeFalse();
});

it('leaves the dedicated cluster and default database out when not given', function () {
    $sentBody = setupDatabaseClusterCreateMocks();

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'laravel_mysql',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->not->toHaveKey('cluster_id')
        ->and($sentBody())->not->toHaveKey('create_default_database');
});

it('rejects an invalid default database value', function () {
    $sentBody = setupDatabaseClusterCreateMocks();

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'laravel_mysql',
        '--region' => 'us-east-1',
        '--create-default-database' => 'maybe',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sentBody())->toBeNull();
});

it('refuses a type without presets when not interactive', function () {
    $sentBody = setupDatabaseClusterCreateMocks();

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'aws_rds_postgres',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sentBody())->toBeNull();
});

it('falls through to the schema prompts for a type without presets when interactive', function () {
    $command = new class extends DatabaseClusterCreate
    {
        protected function isInteractive(): bool
        {
            return true;
        }
    };

    $type = DatabaseType::createFromResponse(['data' => versionlessDatabaseTypesResponse()['data'][1]]);

    expect((fn () => $this->databaseClusterConfigFromPreset($type))->call($command))->toBeNull();
});

it('reads point-in-time recovery details from the cluster response', function () {
    $cluster = DatabaseCluster::createFromResponse(databaseClusterResponse([
        'attributes' => [
            'supports_pitr' => true,
            'earliest_restore_time' => '2026-01-01T00:00:00.000000Z',
        ],
    ]));

    expect($cluster->supportsPitr)->toBeTrue()
        ->and($cluster->earliestRestoreTime?->toIso8601String())->toBe('2026-01-01T00:00:00+00:00');
});
