<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseTypesRequest;
use App\Client\Resources\DatabaseClusters\UpdateDatabaseClusterRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
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

function databaseClusterTypesResponse(): array
{
    return [
        'data' => [
            [
                'type' => 'laravel-mysql',
                'label' => 'MySQL 8',
                'regions' => ['us-east-1'],
                'config_schema' => [
                    ['name' => 'size', 'type' => 'string', 'required' => true, 'example' => 'db-flex.m-1vcpu-512mb'],
                    ['name' => 'storage', 'type' => 'integer', 'required' => true, 'example' => '5'],
                    ['name' => 'retention_days', 'type' => 'integer', 'required' => true, 'example' => '1'],
                    ['name' => 'is_public', 'type' => 'boolean', 'required' => true, 'example' => 'false'],
                ],
            ],
        ],
    ];
}

function setupDatabaseClusterUpdateMocks(): Closure
{
    $sentConfig = new stdClass;

    $cluster = databaseClusterResponse([
        'attributes' => [
            'config' => [
                'size' => 'db-flex.m-1vcpu-512mb',
                'storage' => 5,
                'retention_days' => 1,
                'is_public' => true,
            ],
        ],
    ]);

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetDatabaseClusterRequest::class => MockResponse::make($cluster, 200),
        ListDatabaseTypesRequest::class => MockResponse::make(databaseClusterTypesResponse(), 200),
        UpdateDatabaseClusterRequest::class => function (PendingRequest $request) use ($sentConfig, $cluster) {
            $sentConfig->value = $request->body()->all()['config'];

            return MockResponse::make($cluster, 200);
        },
    ]);

    return fn () => $sentConfig->value ?? null;
}

it('applies a boolean option that was passed as false', function () {
    $sentConfig = setupDatabaseClusterUpdateMocks();

    $this->artisan('database-cluster:update', [
        'cluster' => 'db-123',
        '--is-public' => 'false',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentConfig())->toBe([
        'size' => 'db-flex.m-1vcpu-512mb',
        'storage' => 5,
        'retention_days' => 1,
        'is_public' => false,
    ]);
});

it('applies an integer option and leaves the rest of the config alone', function () {
    $sentConfig = setupDatabaseClusterUpdateMocks();

    $this->artisan('database-cluster:update', [
        'cluster' => 'db-123',
        '--storage' => '20',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentConfig())->toBe([
        'size' => 'db-flex.m-1vcpu-512mb',
        'storage' => 20,
        'retention_days' => 1,
        'is_public' => true,
    ]);
});

it('accepts an option set to the value the cluster already has', function () {
    $sentConfig = setupDatabaseClusterUpdateMocks();

    $this->artisan('database-cluster:update', [
        'cluster' => 'db-123',
        '--retention-days' => '1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentConfig()['retention_days'])->toBe(1);
});

it('fails when no options are given non-interactively', function () {
    $sentConfig = setupDatabaseClusterUpdateMocks();

    $this->artisan('database-cluster:update', [
        'cluster' => 'db-123',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sentConfig())->toBeNull();
});
