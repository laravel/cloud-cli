<?php

use App\Client\Resources\DatabaseClusters\DeleteDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use Illuminate\Support\Sleep;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Sleep::fake();

    $config = Mockery::mock(ConfigRepository::class);
    $config->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $config);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

it('requests databases before deleting a cluster', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetDatabaseClusterRequest::class => MockResponse::make(databaseClusterResponse(), 200),
        DeleteDatabaseClusterRequest::class => MockResponse::make([], 204),
    ]);

    $this->artisan('database-cluster:delete', [
        'database' => 'db-123',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $clusterRequests = collect(MockClient::global()->getRecordedResponses())
        ->map(fn ($response) => $response->getRequest())
        ->filter(fn ($request) => $request instanceof GetDatabaseClusterRequest);

    expect($clusterRequests)->toHaveCount(2);

    $clusterRequests->each(
        fn ($request) => expect($request->query()->get('include'))->toBe('databases'),
    );
});
