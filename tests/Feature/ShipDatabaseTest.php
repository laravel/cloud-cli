<?php

use App\Client\Connector;
use App\Client\Resources\DatabaseClusters\CreateDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseTypesRequest;
use App\Client\Resources\Databases\CreateDatabaseRequest;
use App\Commands\Ship;
use Illuminate\Support\Sleep;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function () {
    Sleep::fake();
});

afterEach(function () {
    MockClient::destroyGlobal();
});

/**
 * Build a non-interactive ship command as it looks once the application exists,
 * right before it provisions the opinionated database.
 */
function shipCommandProvisioningDatabase(array $options): Ship
{
    $command = app(Ship::class);

    // `--no-interaction` is defined by the console application rather than the
    // command, so it is only on the definition once the two are merged.
    $definition = $command->getDefinition();
    $definition->addOption(new InputOption('no-interaction', 'n', InputOption::VALUE_NONE));

    $input = new ArrayInput([...$options, '--no-interaction' => true], $definition);
    $input->setInteractive(false);

    (function () use ($input) {
        $this->input = $input;
        $this->output = new BufferedOutput;
        $this->client = new Connector('test-api-token');
        $this->appName = 'my-app';
        $this->region = 'us-east-1';
    })->call($command);

    return $command;
}

function setupShipDatabaseMocks(): Closure
{
    $sentBody = new stdClass;

    MockClient::global([
        ListDatabaseTypesRequest::class => MockResponse::make(versionlessDatabaseTypesResponse(), 200),
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        CreateDatabaseClusterRequest::class => function (PendingRequest $request) use ($sentBody) {
            $sentBody->value = $request->body()->all();

            return MockResponse::make(databaseClusterResponse([
                'attributes' => ['type' => $sentBody->value['type']],
            ]), 201);
        },
        GetDatabaseClusterRequest::class => MockResponse::make(databaseClusterResponse(), 200),
        CreateDatabaseRequest::class => MockResponse::make(['data' => databaseSchemaResponse()], 201),
    ]);

    return fn () => $sentBody->value ?? null;
}

it('resolves the database option into a type and version', function (string $option, string $type, ?string $version) {
    $command = shipCommandProvisioningDatabase(['--database' => $option]);

    expect((fn () => $this->resolveDatabaseType())->call($command))->toBe([$type, $version]);
})->with([
    'postgres tracks the newest release' => ['postgres', 'neon_serverless_postgres', null],
    'postgres18 pins the version' => ['postgres18', 'neon_serverless_postgres', '18'],
    'postgres17 pins the version' => ['postgres17', 'neon_serverless_postgres', '17'],
    'mysql tracks the newest release' => ['mysql', 'laravel_mysql', null],
    'a versionless API type' => ['neon_serverless_postgres', 'neon_serverless_postgres', null],
    'a retired versioned Postgres type' => ['neon_serverless_postgres_17', 'neon_serverless_postgres', '17'],
    'a retired versioned MySQL type' => ['laravel_mysql_8', 'laravel_mysql', '8.4'],
]);

it('defaults to the newest Serverless Postgres the API offers', function () {
    $sentBody = setupShipDatabaseMocks();

    $command = shipCommandProvisioningDatabase([]);

    $schemaId = (fn () => $this->provisionDatabaseOpinionated())->call($command);

    expect($schemaId)->toBe('schema-1')
        ->and($sentBody()['type'])->toBe('neon_serverless_postgres')
        ->and($sentBody()['version'])->toBe('18')
        ->and($sentBody()['name'])->toBe('my_app')
        ->and($sentBody()['region'])->toBe('us-east-1')
        ->and($sentBody()['config'])->toBe([
            'cu_min' => 0.25,
            'cu_max' => 0.25,
            'suspend_seconds' => 300,
            'retention_days' => 0,
        ]);
});

it('provisions the pinned Postgres version', function () {
    $sentBody = setupShipDatabaseMocks();

    $command = shipCommandProvisioningDatabase(['--database' => 'postgres17', '--database-preset' => 'prod']);

    (fn () => $this->provisionDatabaseOpinionated())->call($command);

    expect($sentBody()['type'])->toBe('neon_serverless_postgres')
        ->and($sentBody()['version'])->toBe('17')
        ->and($sentBody()['config']['retention_days'])->toBe(7);
});

it('still accepts the retired versioned type identifiers', function () {
    $sentBody = setupShipDatabaseMocks();

    $command = shipCommandProvisioningDatabase(['--database' => 'neon_serverless_postgres_18']);

    (fn () => $this->provisionDatabaseOpinionated())->call($command);

    expect($sentBody()['type'])->toBe('neon_serverless_postgres')
        ->and($sentBody()['version'])->toBe('18');
});

it('rejects a version the API does not offer', function () {
    setupShipDatabaseMocks();

    $command = shipCommandProvisioningDatabase(['--database' => 'postgres15']);

    expect(fn () => (fn () => $this->provisionDatabaseOpinionated())->call($command))
        ->toThrow(RuntimeException::class, 'Version "15" is not available for database type "neon_serverless_postgres". Available versions: 16, 17, 18');

    MockClient::global()->assertNotSent(CreateDatabaseClusterRequest::class);
});

it('rejects a database type it has no presets for', function () {
    setupShipDatabaseMocks();

    $command = shipCommandProvisioningDatabase(['--database' => 'aws_rds_postgres']);

    expect(fn () => (fn () => $this->provisionDatabaseOpinionated())->call($command))
        ->toThrow(RuntimeException::class, 'Invalid --database value "aws_rds_postgres"');
});
