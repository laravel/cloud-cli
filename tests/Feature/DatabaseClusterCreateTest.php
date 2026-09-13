<?php

use App\Client\Resources\DatabaseClusters\CreateDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseTypesRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
use App\ConfigRepository;
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
