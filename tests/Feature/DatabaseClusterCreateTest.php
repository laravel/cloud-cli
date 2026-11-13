<?php

use App\Client\Resources\DatabaseClusters\CreateDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabasePresetsRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseTypesRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
use App\Commands\DatabaseClusterCreate;
use App\ConfigRepository;
use App\Dto\DatabaseCluster;
use App\Dto\DatabasePreset;
use App\Dto\DatabaseType;
use App\Enums\DatabaseClusterPreset;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\Fixtures\InteractiveDatabaseClusterCreateCommand;

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
        ListDatabasePresetsRequest::class => fn (PendingRequest $request) => MockResponse::make(
            databasePresetsResponse($request->query()->get('type')),
            200,
        ),
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

it('sends the config of the first preset the API offers for the type and region', function () {
    $sentBody = setupDatabaseClusterCreateMocks();

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'laravel_mysql',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(fn ($request) => $request instanceof ListDatabasePresetsRequest
        && $request->query()->all() === ['type' => 'laravel_mysql', 'region' => 'us-east-1']);

    expect($sentBody()['config'])->toBe(databasePresetsResponse('laravel_mysql')['data'][0]['config']);
});

it('creates an RDS cluster from the presets the API offers', function () {
    $sentBody = setupDatabaseClusterCreateMocks();

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'aws_rds_postgres',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody()['type'])->toBe('aws_rds_postgres')
        ->and($sentBody()['config'])->toBe(databasePresetsResponse('aws_rds_postgres')['data'][0]['config']);
});

it('rejects a retired type before fetching presets', function () {
    $sentBody = setupDatabaseClusterCreateMocks();

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'laravel_mysql_84',
        '--engine-version' => '8.4',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertFailed();

    MockClient::global()->assertNotSent(ListDatabasePresetsRequest::class);

    expect($sentBody())->toBeNull();
});

it('leaves retired types out of the type picker', function () {
    Artisan::registerCommand(new InteractiveDatabaseClusterCreateCommand);

    $sentBody = setupDatabaseClusterCreateMocks();

    Prompt::fake([
        // Wraps to the last option, which would be a retired type if the picker listed them.
        Key::UP,
        Key::ENTER,
        // Accept the first preset.
        Key::ENTER,
    ]);

    $this->artisan('test:interactive-database-cluster-create', [
        '--name' => 'my-cluster',
        '--engine-version' => '18',
        '--region' => 'us-east-1',
    ])->assertSuccessful();

    expect($sentBody()['type'])->toBe('neon_serverless_postgres');
});

it('fails when the API offers no presets and the run is not interactive', function () {
    $sentBody = setupDatabaseClusterCreateMocks();

    MockClient::global()->addResponses([
        ListDatabasePresetsRequest::class => MockResponse::make(['data' => []], 200),
    ]);

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'laravel_mysql',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sentBody())->toBeNull();
});

it('uses the requested preset when not interactive', function () {
    $sentBody = setupDatabaseClusterCreateMocks();

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'laravel_mysql',
        '--region' => 'us-east-1',
        '--preset' => 'PROD',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody()['config'])->toBe(databasePresetsResponse('laravel_mysql')['data'][1]['config']);
});

it('rejects a preset the API does not offer', function () {
    $sentBody = setupDatabaseClusterCreateMocks();

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'laravel_mysql',
        '--region' => 'us-east-1',
        '--preset' => 'huge',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sentBody())->toBeNull();
});

it('lets config flags override the preset', function () {
    $sentBody = setupDatabaseClusterCreateMocks();

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'laravel_mysql',
        '--region' => 'us-east-1',
        '--size' => 'mysql-pro-4gb',
        '--storage' => '50',
        '--is-public' => 'true',
        '--maintenance-window' => 'sun:06:00-sun:06:30',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody()['config'])->toBe([
        'size' => 'mysql-pro-4gb',
        'storage' => 50,
        'retention_days' => 1,
        'uses_scheduled_snapshots' => false,
        'is_public' => true,
        'suspend_seconds' => 300,
        'maintenance_window' => 'sun:06:00-sun:06:30',
    ]);
});

it('sends Neon compute flags as numbers', function () {
    $sentBody = setupDatabaseClusterCreateMocks();

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'neon_serverless_postgres',
        '--region' => 'us-east-1',
        '--cu-max' => '2',
        '--suspend-seconds' => '0',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody()['config'])->toBe([
        'cu_min' => 0.25,
        'cu_max' => 2.0,
        'suspend_seconds' => 0,
        'retention_days' => 0,
    ]);
});

it('rejects a config flag that does not apply to the type', function () {
    $sentBody = setupDatabaseClusterCreateMocks();

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'laravel_mysql',
        '--region' => 'us-east-1',
        '--cu-min' => '1',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sentBody())->toBeNull();
});

it('rejects a config flag value that does not parse', function (string $option, string $value) {
    $sentBody = setupDatabaseClusterCreateMocks();

    $this->artisan('database-cluster:create', [
        '--name' => 'my-cluster',
        '--type' => 'laravel_mysql',
        '--region' => 'us-east-1',
        $option => $value,
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sentBody())->toBeNull();
})->with([
    'boolean' => ['--is-public', 'ture'],
    'integer' => ['--storage', 'abc'],
]);

it('sends the corrected custom value after the API rejects a flag', function () {
    Artisan::registerCommand(new InteractiveDatabaseClusterCreateCommand);

    setupDatabaseClusterCreateMocks();

    $bodies = [];

    MockClient::global()->addResponses([
        CreateDatabaseClusterRequest::class => function (PendingRequest $request) use (&$bodies) {
            $bodies[] = $request->body()->all();

            return count($bodies) === 1
                ? MockResponse::make(['message' => 'Invalid size.', 'errors' => ['config.size' => ['The selected size is invalid.']]], 422)
                : MockResponse::make(databaseClusterResponse(), 201);
        },
    ]);

    Prompt::fake([
        // Custom is the last configuration option.
        Key::UP, Key::ENTER,
        // Accept the defaults for storage, retention days, scheduled snapshots, public, maintenance window and suspend seconds.
        Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER,
        // Replace the rejected size.
        ...array_fill(0, strlen('bogus'), Key::BACKSPACE), ...str_split('db-flex.m-1vcpu-1gb'), Key::ENTER,
    ]);

    $this->artisan('test:interactive-database-cluster-create', [
        '--name' => 'my-cluster',
        '--type' => 'laravel_mysql',
        '--engine-version' => '8.4',
        '--region' => 'us-east-1',
        '--size' => 'bogus',
    ])->assertSuccessful();

    expect($bodies)->toHaveCount(2)
        ->and($bodies[0]['config']['size'])->toBe('bogus')
        ->and($bodies[1]['config']['size'])->toBe('db-flex.m-1vcpu-1gb');
});

it('summarises a MySQL preset from its API size', function () {
    $summary = DatabaseClusterPreset::LaravelMysql->description()(databasePresetsResponse('laravel_mysql')['data'][0]['config']);

    expect($summary)->toBe('Flex (512 MiB RAM) · 5GB storage · 1 day backups');
});

it('lists the config of a preset for a type without a hand-written summary', function () {
    $type = DatabaseType::createFromResponse(['data' => versionlessDatabaseTypesResponse()['data'][1]]);
    $preset = DatabasePreset::from(databasePresetsResponse('aws_rds_postgres')['data'][0]);

    $summary = (fn () => $this->describeDatabasePreset($type, $preset))->call(new DatabaseClusterCreate);

    expect($summary)->toBe('size: db.t4g.micro · storage: 20 · multi az: false');
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
