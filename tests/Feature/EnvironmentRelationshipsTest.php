<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\UpdateEnvironmentRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use App\Git;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->mockGit->shouldReceive('currentBranch')->andReturn('main')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

/**
 * The API returns a relationship only when the request asked to include it, so
 * the fake has to drop anything the caller did not request.
 */
function attachedEnvironmentResponse(?string $include, array $bucketIds = []): array
{
    $requested = explode(',', $include ?? '');

    $relationships = array_filter([
        'application' => ['data' => ['id' => 'app-123', 'type' => 'applications']],
        'database' => ['data' => ['id' => '65634813', 'type' => 'databaseSchemas']],
        'cache' => ['data' => null],
        'websocketApplication' => ['data' => null],
        'buckets' => ['data' => array_map(fn (string $id) => ['id' => $id, 'type' => 'filesystems'], $bucketIds)],
    ], fn ($key) => in_array($key, $requested, true), ARRAY_FILTER_USE_KEY);

    return [
        'data' => createEnvironmentResponse(['relationships' => $relationships]),
        'included' => [createApplicationResponse()],
    ];
}

function captureEnvironmentIncludes(): Closure
{
    $captured = new stdClass;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetApplicationRequest::class => MockResponse::make(['data' => createApplicationResponse()], 200),
        GetEnvironmentRequest::class => function (PendingRequest $request) use ($captured) {
            $captured->value = $request->query()->get('include');

            return MockResponse::make(attachedEnvironmentResponse($captured->value), 200);
        },
        UpdateEnvironmentRequest::class => MockResponse::make(attachedEnvironmentResponse(null), 200),
    ]);

    return fn () => $captured->value ?? null;
}

it('requests the database, cache and websocket relationships when getting an environment', function () {
    $includes = captureEnvironmentIncludes();

    $this->artisan('environment:get', [
        'environment' => 'env-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(explode(',', $includes()))
        ->toContain('database')
        ->toContain('cache')
        ->toContain('websocketApplication');
});

it('reads the attached database schema id from the environment relationships', function () {
    captureEnvironmentIncludes();

    $this->artisan('environment:get', [
        'environment' => 'env-1',
        '--json' => true,
        '--fields' => 'databaseSchemaId',
        '--no-interaction' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"databaseSchemaId":"65634813"');
});

it('reports the attached database after updating an environment', function () {
    $sentDatabaseSchemaId = new stdClass;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetApplicationRequest::class => MockResponse::make(['data' => createApplicationResponse()], 200),
        GetEnvironmentRequest::class => fn (PendingRequest $request) => MockResponse::make(
            attachedEnvironmentResponse($request->query()->get('include')),
            200,
        ),
        UpdateEnvironmentRequest::class => function (PendingRequest $request) use ($sentDatabaseSchemaId) {
            $sentDatabaseSchemaId->value = $request->body()->all()['database_schema_id'] ?? null;

            return MockResponse::make(attachedEnvironmentResponse(null), 200);
        },
    ]);

    $this->artisan('environment:update', [
        'environment' => 'env-1',
        '--database-id' => '65634813',
        '--json' => true,
        '--fields' => 'databaseSchemaId',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"databaseSchemaId":"65634813"');

    expect($sentDatabaseSchemaId->value)->toBe('65634813');
});

it('reads the attached bucket ids from the environment relationships', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetApplicationRequest::class => MockResponse::make(['data' => createApplicationResponse()], 200),
        GetEnvironmentRequest::class => fn (PendingRequest $request) => MockResponse::make(
            attachedEnvironmentResponse($request->query()->get('include'), ['fs-1']),
            200,
        ),
    ]);

    $this->artisan('environment:get', [
        'environment' => 'env-1',
        '--json' => true,
        '--fields' => 'bucketIds',
        '--no-interaction' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"bucketIds":["fs-1"]');
});

/**
 * @return stdClass whose `body` holds the update request body, or stays unset when no update was sent
 */
function captureBucketUpdate(array $attachedBucketIds): stdClass
{
    $sent = new stdClass;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetApplicationRequest::class => MockResponse::make(['data' => createApplicationResponse()], 200),
        GetEnvironmentRequest::class => fn (PendingRequest $request) => MockResponse::make(
            attachedEnvironmentResponse($request->query()->get('include'), $attachedBucketIds),
            200,
        ),
        UpdateEnvironmentRequest::class => function (PendingRequest $request) use ($sent) {
            $sent->body = $request->body()->all();

            return MockResponse::make(attachedEnvironmentResponse(null), 200);
        },
    ]);

    return $sent;
}

it('attaches several bucket keys with their disks', function () {
    $sent = captureBucketUpdate([]);

    $this->artisan('environment:update', [
        'environment' => 'env-1',
        '--bucket-keys' => 'fk-1:local:default,fk-2:public',
        '--json' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body['filesystem_keys'])->toBe([
        ['id' => 'fk-1', 'disk' => 'local', 'is_default_disk' => true],
        ['id' => 'fk-2', 'disk' => 'public', 'is_default_disk' => false],
    ]);
});

it('refuses to replace attached buckets without --force', function () {
    $sent = captureBucketUpdate(['fs-existing']);

    $this->artisan('environment:update', [
        'environment' => 'env-1',
        '--bucket-keys' => 'fk-1:local',
        '--json' => true,
        '--no-interaction' => true,
    ])->assertFailed();

    expect(isset($sent->body))->toBeFalse();
});

it('replaces attached buckets with --force', function () {
    $sent = captureBucketUpdate(['fs-existing']);

    $this->artisan('environment:update', [
        'environment' => 'env-1',
        '--bucket-keys' => 'fk-1:local',
        '--force' => true,
        '--json' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body['filesystem_keys'])->toBe([['id' => 'fk-1', 'disk' => 'local', 'is_default_disk' => false]]);
});

it('detaches every bucket when given an empty list', function () {
    $sent = captureBucketUpdate(['fs-existing']);

    $this->artisan('environment:update', [
        'environment' => 'env-1',
        '--bucket-keys' => '',
        '--force' => true,
        '--json' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body)->toHaveKey('filesystem_keys')
        ->and($sent->body['filesystem_keys'])->toBe([]);
});

it('rejects bucket key entries without a disk or with two default disks', function (string $value) {
    $sent = captureBucketUpdate([]);

    $this->artisan('environment:update', [
        'environment' => 'env-1',
        '--bucket-keys' => $value,
        '--json' => true,
        '--no-interaction' => true,
    ])->assertFailed();

    expect(isset($sent->body))->toBeFalse();
})->with([
    'missing disk' => 'fk-1',
    'unknown suffix' => 'fk-1:local:primary',
    'two defaults' => 'fk-1:local:default,fk-2:public:default',
]);
