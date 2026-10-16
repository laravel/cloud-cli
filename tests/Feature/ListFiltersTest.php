<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Caches\ListCachesRequest;
use App\Client\Resources\Commands\ListCommandsRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\DedicatedClusters\ListDedicatedClustersRequest;
use App\Client\Resources\Deployments\ListDeploymentsRequest;
use App\Client\Resources\Domains\ListDomainsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\Client\Resources\Instances\ListInstancesRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Secrets\ListSecretsRequest;
use App\Client\Resources\WebSocketClusters\ListWebSocketClustersRequest;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;

beforeEach(function () {
    $config = Mockery::mock(ConfigRepository::class);
    $config->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $config);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

/**
 * @param  class-string<Request>  $listRequest
 */
function setupListFilterMocks(string $listRequest, array $items = []): void
{
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetApplicationRequest::class => MockResponse::make(['data' => createApplicationResponse()], 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()], 200),
        $listRequest => MockResponse::make(['data' => $items, 'included' => [], 'links' => ['next' => null]], 200),
    ]);
}

it('sends list filters in the query string', function (string $command, array $arguments, string $listRequest, array $expected) {
    setupListFilterMocks($listRequest);

    $this->artisan($command, [...$arguments, '--json' => true, '--no-interaction' => true])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) use ($listRequest, $expected) {
        return $request instanceof $listRequest
            && array_intersect_assoc($request->query()->all(), $expected) == $expected;
    });
})->with([
    'application:list' => ['application:list', ['--name' => 'shop', '--region' => 'us-east-1', '--slug' => 'shop'], ListApplicationsRequest::class, [
        'filter[name]' => 'shop', 'filter[region]' => 'us-east-1', 'filter[slug]' => 'shop',
    ]],
    'environment:list' => ['environment:list', ['application' => 'app-123', '--name' => 'staging', '--status' => 'running', '--slug' => 'staging'], ListEnvironmentsRequest::class, [
        'filter[name]' => 'staging', 'filter[status]' => 'running', 'filter[slug]' => 'staging',
    ]],
    'deployment:list' => ['deployment:list', ['environment' => 'env-1', '--status' => 'deployment.succeeded', '--branch' => 'main', '--commit' => 'abc123'], ListDeploymentsRequest::class, [
        'filter[status]' => 'deployment.succeeded', 'filter[branch_name]' => 'main', 'filter[commit_hash]' => 'abc123',
    ]],
    'command:list' => ['command:list', ['environment' => 'env-1', '--status' => 'command.failure', '--command' => 'php artisan migrate'], ListCommandsRequest::class, [
        'filter[status]' => 'command.failure', 'filter[command]' => 'php artisan migrate',
    ]],
    'domain:list' => ['domain:list', ['environment' => 'env-1', '--name' => 'example.com', '--hostname-status' => 'verified', '--ssl-status' => 'pending', '--origin-status' => 'failed'], ListDomainsRequest::class, [
        'filter[name]' => 'example.com', 'filter[hostname_status]' => 'verified', 'filter[ssl_status]' => 'pending', 'filter[origin_status]' => 'failed',
    ]],
    'instance:list' => ['instance:list', ['environment' => 'env-1', '--name' => 'web', '--type' => 'app', '--size' => 'flex-1gb', '--scaling-type' => 'auto'], ListInstancesRequest::class, [
        'filter[name]' => 'web', 'filter[type]' => 'app', 'filter[size]' => 'flex-1gb', 'filter[scaling_type]' => 'auto',
    ]],
    'cache:list' => ['cache:list', ['--type' => 'laravel_valkey', '--region' => 'us-east-1', '--status' => 'available'], ListCachesRequest::class, [
        'filter[type]' => 'laravel_valkey', 'filter[region]' => 'us-east-1', 'filter[status]' => 'available',
    ]],
    'database-cluster:list' => ['database-cluster:list', ['--type' => 'laravel_mysql', '--region' => 'us-east-1', '--status' => 'available'], ListDatabaseClustersRequest::class, [
        'filter[type]' => 'laravel_mysql', 'filter[region]' => 'us-east-1', 'filter[status]' => 'available', 'include' => 'databases',
    ]],
    'websocket-cluster:list' => ['websocket-cluster:list', ['--type' => 'reverb', '--region' => 'us-east-1', '--status' => 'available'], ListWebSocketClustersRequest::class, [
        'filter[type]' => 'reverb', 'filter[region]' => 'us-east-1', 'filter[status]' => 'available',
    ]],
    'dedicated-cluster:list' => ['dedicated-cluster:list', ['--region' => 'us-east-1', '--type' => 'applications', '--status' => 'active'], ListDedicatedClustersRequest::class, [
        'filter[region]' => 'us-east-1', 'filter[type]' => 'applications', 'filter[status]' => 'active',
    ]],
    'secret:list' => ['secret:list', ['--key' => 'STRIPE', '--sort' => '-created_at'], ListSecretsRequest::class, [
        'filter[key]' => 'STRIPE', 'sort' => '-created_at',
    ]],
]);

it('sends no filters when none are given', function () {
    setupListFilterMocks(ListCachesRequest::class);

    $this->artisan('cache:list', ['--json' => true, '--no-interaction' => true])->assertSuccessful();

    MockClient::global()->assertSent(fn ($request) => $request instanceof ListCachesRequest && $request->query()->all() === ['page' => 1]);
});

it('rejects a filter value outside the documented values', function () {
    setupListFilterMocks(ListDeploymentsRequest::class);

    $this->artisan('deployment:list', ['environment' => 'env-1', '--status' => 'done', '--json' => true, '--no-interaction' => true])
        ->assertFailed();

    MockClient::global()->assertNotSent(ListDeploymentsRequest::class);
});

it('includes type, tenancy type, and created at for dedicated clusters', function () {
    setupListFilterMocks(ListDedicatedClustersRequest::class, [[
        'id' => 'cluster-1',
        'type' => 'clusters',
        'attributes' => [
            'name' => 'Primary',
            'region' => 'us-east-1',
            'type' => 'applications',
            'tenancy_type' => 'dedicated',
            'status' => 'active',
            'created_at' => '2026-01-02T03:04:05.000000Z',
        ],
    ]]);

    Artisan::call('dedicated-cluster:list', ['--json' => true]);

    $cluster = json_decode(Artisan::output(), true)[0];

    expect($cluster)->toMatchArray([
        'id' => 'cluster-1',
        'type' => 'applications',
        'tenancyType' => 'dedicated',
        'status' => 'active',
    ])->and($cluster['createdAt'])->toStartWith('2026-01-02T03:04:05');
});
