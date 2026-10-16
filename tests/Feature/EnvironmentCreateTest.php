<?php

use App\Client\Connector;
use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\EdgeNetworks\ListEdgeNetworksRequest;
use App\Client\Resources\Environments\CreateEnvironmentRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use App\Git;
use App\LocalConfig;
use App\Prompts\Renderer;
use App\Resolvers\EdgeNetworkResolver;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    Renderer::$suppressOutput = false;

    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('currentBranch')->andReturn('main')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    $this->localConfig = Mockery::mock(LocalConfig::class);
    $this->localConfig->shouldReceive('get')->andReturn(null)->byDefault();
    $this->localConfig->shouldReceive('applicationId')->andReturn(null)->byDefault();
    $this->localConfig->shouldReceive('environmentId')->andReturn(null)->byDefault();
    $this->app->instance(LocalConfig::class, $this->localConfig);

    $this->edgeNetworks = [
        edgeNetworkResponse(),
        edgeNetworkResponse(['id' => 'en-dedicated', 'attributes' => ['name' => 'Acme Edge', 'domain' => 'acme.test', 'tenancy_type' => 'dedicated']]),
    ];

    $this->sentBody = null;
    $this->edgeNetworkQuery = null;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetApplicationRequest::class => MockResponse::make(['data' => createApplicationResponse()], 200),
        ListEdgeNetworksRequest::class => function (PendingRequest $request) {
            $this->edgeNetworkQuery = $request->query()->all();

            return MockResponse::make(['data' => $this->edgeNetworks, 'links' => ['next' => null]], 200);
        },
        CreateEnvironmentRequest::class => function (PendingRequest $request) {
            $this->sentBody = $request->body()?->all();

            return MockResponse::make(['data' => createEnvironmentResponse()], 201);
        },
        GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()], 200),
    ]);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

it('sends only name and branch by default', function () {
    $this->artisan('environment:create', [
        'application' => 'app-123',
        '--name' => 'staging',
        '--branch' => 'develop',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sentBody)->toBe(['name' => 'staging', 'branch' => 'develop']);
    MockClient::global()->assertNotSent(ListEdgeNetworksRequest::class);
});

it('sends the build command, cluster and edge network', function (string $edgeNetwork) {
    $this->artisan('environment:create', [
        'application' => 'app-123',
        '--name' => 'staging',
        '--branch' => 'develop',
        '--build-command' => 'npm run build',
        '--cluster' => 'cluster-1',
        '--edge-network' => $edgeNetwork,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sentBody)->toBe([
        'name' => 'staging',
        'branch' => 'develop',
        'build_command' => 'npm run build',
        'cluster_id' => 'cluster-1',
        'edge_network_id' => 'en-dedicated',
    ]);
})->with(['by id' => 'en-dedicated', 'by name' => 'Acme Edge']);

it('fails without creating when the edge network is unknown', function () {
    $this->artisan('environment:create', [
        'application' => 'app-123',
        '--name' => 'staging',
        '--branch' => 'develop',
        '--edge-network' => 'nope',
        '--no-interaction' => true,
    ])->assertFailed();

    MockClient::global()->assertNotSent(CreateEnvironmentRequest::class);
});

function edgeNetworkResolver(bool $interactive): EdgeNetworkResolver
{
    return new EdgeNetworkResolver(new Connector('test-api-token'), test()->localConfig, $interactive);
}

it('does not ask for an edge network when only the shared one exists', function () {
    $this->edgeNetworks = [edgeNetworkResponse()];

    expect(edgeNetworkResolver(interactive: true)->from())->toBeNull();
});

it('asks for an edge network when the organization has its own', function () {
    Prompt::fake([Key::DOWN, Key::DOWN, Key::ENTER]);

    expect(edgeNetworkResolver(interactive: true)->from()->id)->toBe('en-dedicated');

    Prompt::assertOutputContains('Organization default');
});

it('leaves the edge network to the organization default when chosen', function () {
    Prompt::fake([Key::ENTER]);

    expect(edgeNetworkResolver(interactive: true)->from())->toBeNull();
});

it('lists edge networks with filters', function () {
    $this->artisan('edge-network:list', ['--status' => 'available', '--json' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('en-dedicated');

    expect($this->edgeNetworkQuery)->toMatchArray(['filter[status]' => 'available']);
});
