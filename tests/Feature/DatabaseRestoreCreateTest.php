<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseRestores\CreateDatabaseRestoreRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use Illuminate\Support\Sleep;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Sleep::fake();

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function setupDatabaseRestoreMocks(int $createStatus = 200, ?array $createBody = null): void
{
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetDatabaseClusterRequest::class => MockResponse::make(databaseClusterResponse(), 200),
        CreateDatabaseRestoreRequest::class => MockResponse::make(
            $createBody ?? databaseClusterResponse([
                'id' => 'db-456',
                'attributes' => ['name' => 'my-restore'],
            ]),
            $createStatus,
        ),
    ]);
}

it('creates a restore from a snapshot non-interactively', function () {
    setupDatabaseRestoreMocks();

    $this->artisan('database-restore:create', [
        'cluster' => 'db-123',
        'name' => 'my-restore',
        '--snapshot' => 'snap-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('creates a restore from a point-in-time non-interactively', function () {
    setupDatabaseRestoreMocks();

    $this->artisan('database-restore:create', [
        'cluster' => 'db-123',
        'name' => 'my-restore',
        '--point-in-time' => '2024-01-15T12:00:00Z',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fails when neither snapshot nor point-in-time is given non-interactively', function () {
    setupDatabaseRestoreMocks();

    $this->artisan('database-restore:create', [
        'cluster' => 'db-123',
        'name' => 'my-restore',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('fails with JSON validation errors when the API returns 422', function () {
    setupDatabaseRestoreMocks(422, [
        'message' => 'Validation failed',
        'errors' => ['name' => ['Name has already been taken.']],
    ]);

    $result = $this->artisan('database-restore:create', [
        'cluster' => 'db-123',
        'name' => 'taken',
        '--snapshot' => 'snap-1',
        '--json' => true,
    ]);

    $result->assertFailed();
});
