<?php

use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
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

it('requests databases when listing database clusters', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('database-cluster:list', [
        '--json' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof ListDatabaseClustersRequest
            && $request->query()->get('include') === 'databases';
    });
});

it('reads schemas out of the databases include', function () {
    $cluster = databaseClusterResponse([
        'included' => [
            databaseSchemaResponse(['id' => 'schema-1', 'attributes' => ['name' => 'first']]),
            databaseSchemaResponse(['id' => 'schema-2', 'attributes' => ['name' => 'second']]),
        ],
    ]);

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [$cluster['data']],
            'included' => $cluster['included'],
            'links' => ['next' => null],
        ], 200),
    ]);

    $exitCode = Artisan::call('database-cluster:list', [
        '--json' => true,
        '--no-interaction' => true,
    ]);

    expect($exitCode)->toBe(0);

    $output = json_decode(Artisan::output(), true);

    expect($output[0]['schemas'])->toBe([
        ['id' => 'schema-1', 'name' => 'first', 'createdAt' => '2024-01-15T12:00:00.000000Z'],
        ['id' => 'schema-2', 'name' => 'second', 'createdAt' => '2024-01-15T12:00:00.000000Z'],
    ]);
});
