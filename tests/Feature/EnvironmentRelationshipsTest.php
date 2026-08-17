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
function attachedEnvironmentResponse(?string $include): array
{
    $requested = explode(',', $include ?? '');

    $relationships = array_filter([
        'application' => ['data' => ['id' => 'app-123', 'type' => 'applications']],
        'database' => ['data' => ['id' => '65634813', 'type' => 'databaseSchemas']],
        'cache' => ['data' => null],
        'websocketApplication' => ['data' => null],
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
