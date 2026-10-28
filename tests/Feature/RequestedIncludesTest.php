<?php

use App\Client\Connector;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Caches\GetCacheRequest;
use App\Client\Resources\Caches\ListCachesRequest;
use App\Client\Resources\Caches\ListCacheTypesRequest;
use App\Client\Resources\Deployments\GetDeploymentRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    $config = Mockery::mock(ConfigRepository::class);
    $config->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $config);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

/**
 * Answers the way the API does: a relationship, and the resources it points at, appear
 * only when the request names it in `include`.
 *
 * @param  array<string, array>  $relationships  Relationship name => its `data`.
 * @param  array<string, list<array>>  $included  Relationship name => the resources it adds to `included`.
 */
function respondWithRequestedIncludes(array $resource, array $relationships, array $included = [], bool $single = false): Closure
{
    return function (PendingRequest $request) use ($resource, $relationships, $included, $single) {
        $requested = array_flip(array_filter(explode(',', (string) $request->query()->get('include'))));

        $resource['relationships'] = array_map(
            fn (array $data) => ['data' => $data],
            array_intersect_key($relationships, $requested),
        );

        return MockResponse::make([
            'data' => $single ? $resource : [$resource],
            'included' => array_merge([], ...array_values(array_intersect_key($included, $requested))),
            'links' => ['next' => null],
        ]);
    };
}

function connectorWith(MockClient $mockClient): Connector
{
    $connector = new Connector('test-api-token');
    $connector->withMockClient($mockClient);

    return $connector;
}

it('lists the environments attached to each cache', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse()),
        ListCachesRequest::class => respondWithRequestedIncludes(cacheResponse(), [
            'environments' => [['id' => 'env-1', 'type' => 'environments']],
        ]),
    ]);

    Artisan::call('cache:list', ['--json' => true]);

    expect(json_decode(Artisan::output(), true)[0]['environmentIds'])->toBe(['env-1']);
});

it('gets the environments attached to a cache', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse()),
        GetCacheRequest::class => respondWithRequestedIncludes(cacheResponse(), [
            'environments' => [['id' => 'env-1', 'type' => 'environments']],
        ], single: true),
    ]);

    Artisan::call('cache:get', ['cache' => 'cache-1', '--json' => true]);

    expect(json_decode(Artisan::output(), true)['environmentIds'])->toBe(['env-1']);
});

it('sends no includes when listing cache types', function () {
    $mockClient = new MockClient([
        ListCacheTypesRequest::class => MockResponse::make(['data' => []]),
    ]);

    connectorWith($mockClient)->caches()->types();

    $mockClient->assertSent(fn ($request, $response) => $response->getPendingRequest()->query()->get('include') === null);
});

it('lists the default environment of each application', function () {
    $application = createApplicationResponse();
    unset($application['relationships']);

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse()),
        ListApplicationsRequest::class => respondWithRequestedIncludes($application, [
            'organization' => ['id' => 'org-1', 'type' => 'organizations'],
            'environments' => [['id' => 'env-1', 'type' => 'environments']],
            'defaultEnvironment' => ['id' => 'env-1', 'type' => 'environments'],
        ]),
    ]);

    Artisan::call('application:list', ['--json' => true]);

    expect(json_decode(Artisan::output(), true)[0]['defaultEnvironmentId'])->toBe('env-1');
});

it('shows who started a deployment', function () {
    $user = ['id' => 'user-1', 'type' => 'users', 'attributes' => ['name' => 'Taylor', 'email' => 'taylor@example.com']];

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse()),
        GetDeploymentRequest::class => respondWithRequestedIncludes(
            ['id' => 'depl-1', 'type' => 'deployments', 'attributes' => ['status' => 'deployment.succeeded']],
            [
                'environment' => ['id' => 'env-1', 'type' => 'environments'],
                'initiator' => ['id' => 'user-1', 'type' => 'users'],
            ],
            ['environment' => [createEnvironmentResponse()], 'initiator' => [$user]],
            single: true,
        ),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => createEnvironmentResponse(['relationships' => [
                'application' => ['data' => ['id' => 'app-123', 'type' => 'applications']],
            ]]),
            'included' => [createApplicationResponse()],
        ]),
    ]);

    Artisan::call('deployment:get', ['deployment' => 'depl-1', '--json' => true]);

    $deployment = json_decode(Artisan::output(), true);

    expect($deployment['initiator']['name'])->toBe('Taylor')
        ->and($deployment['environment']['id'])->toBe('env-1');
});

it('fills the initiator of listed deployments', function () {
    $user = ['id' => 'user-1', 'type' => 'users', 'attributes' => ['name' => 'Taylor', 'email' => 'taylor@example.com']];

    $connector = connectorWith(new MockClient([
        respondWithRequestedIncludes(
            ['id' => 'depl-1', 'type' => 'deployments', 'attributes' => ['status' => 'deployment.succeeded']],
            ['initiator' => ['id' => 'user-1', 'type' => 'users']],
            ['initiator' => [$user]],
        ),
    ]));

    $deployment = $connector->deployments()->list('env-1')->collect()->first();

    expect($deployment->initiator?->name)->toBe('Taylor');
});

it('fills the keys of a bucket', function (string $method) {
    $connector = connectorWith(new MockClient([
        respondWithRequestedIncludes(bucketResponse(), [
            'keys' => [['id' => 'flsk-1', 'type' => 'filesystemKeys']],
        ], single: $method === 'get'),
    ]));

    $bucket = $method === 'get'
        ? $connector->objectStorageBuckets()->get('fls-1')
        : $connector->objectStorageBuckets()->list()->collect()->first();

    expect($bucket->keyIds)->toBe(['flsk-1']);
})->with(['get', 'list']);

it('fills the applications of a WebSocket cluster', function (string $method) {
    $connector = connectorWith(new MockClient([
        respondWithRequestedIncludes(websocketClusterResponse(), [
            'applications' => [['id' => 'wsa-1', 'type' => 'websocketApplications']],
        ], single: $method === 'get'),
    ]));

    $cluster = $method === 'get'
        ? $connector->websocketClusters()->get('wss-1')
        : $connector->websocketClusters()->list()->collect()->first();

    expect($cluster->applicationIds)->toBe(['wsa-1']);
})->with(['get', 'list']);

it('fills the cluster of a WebSocket application', function (string $method) {
    $connector = connectorWith(new MockClient([
        respondWithRequestedIncludes(websocketApplicationResponse(), [
            'server' => ['id' => 'wss-1', 'type' => 'websocketServers'],
        ], ['server' => [websocketClusterResponse()]], single: $method === 'get'),
    ]));

    $application = $method === 'get'
        ? $connector->websocketApplications()->get('wsa-1')
        : $connector->websocketApplications()->list('wss-1')->collect()->first();

    expect($application->server?->id)->toBe('wss-1');
})->with(['get', 'list']);

it('fills the environment of a domain', function (string $method) {
    $connector = connectorWith(new MockClient([
        respondWithRequestedIncludes(
            ['id' => 'domain-1', 'type' => 'domains', 'attributes' => ['name' => 'example.com']],
            ['environment' => ['id' => 'env-1', 'type' => 'environments']],
            single: $method === 'get',
        ),
    ]));

    $domain = $method === 'get'
        ? $connector->domains()->get('domain-1')
        : $connector->domains()->list('env-1')->collect()->first();

    expect($domain->environmentId)->toBe('env-1');
})->with(['get', 'list']);
