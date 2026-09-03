<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseSnapshots\DeleteDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\GetDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\ListDatabaseSnapshotsRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
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

function setupDatabaseSnapshotMocks(): void
{
    $snapshot = databaseSnapshotResponse();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetDatabaseClusterRequest::class => MockResponse::make(databaseClusterResponse(), 200),
        ListDatabaseSnapshotsRequest::class => MockResponse::make([
            'data' => [$snapshot],
            'links' => ['next' => null],
        ], 200),
        GetDatabaseSnapshotRequest::class => MockResponse::make(['data' => $snapshot], 200),
        DeleteDatabaseSnapshotRequest::class => MockResponse::make([], 204),
    ]);
}

it('fetches a snapshot from the canonical endpoint', function () {
    setupDatabaseSnapshotMocks();

    $this->artisan('database-snapshot:get', [
        'cluster' => 'db-123',
        'snapshot' => 'snap-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof GetDatabaseSnapshotRequest
            && $request->resolveEndpoint() === '/database-snapshots/snap-1';
    });
});

it('deletes a snapshot at the canonical endpoint', function () {
    setupDatabaseSnapshotMocks();

    $this->artisan('database-snapshot:delete', [
        'cluster' => 'db-123',
        'snapshot' => 'snap-1',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof DeleteDatabaseSnapshotRequest
            && $request->resolveEndpoint() === '/database-snapshots/snap-1';
    });
});

it('still lists snapshots under the cluster', function () {
    setupDatabaseSnapshotMocks();

    $this->artisan('database-snapshot:list', [
        'cluster' => 'db-123',
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof ListDatabaseSnapshotsRequest
            && $request->resolveEndpoint() === '/databases/clusters/db-123/snapshots';
    });
});
