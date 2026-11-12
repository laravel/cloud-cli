<?php

use App\Client\Connector;
use App\Client\Resources\Applications\CreateApplicationRequest;
use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\DatabaseClusters\CreateDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\DatabaseClusters\ListDatabasePresetsRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseTypesRequest;
use App\Client\Resources\Databases\CreateDatabaseRequest;
use App\Client\Resources\Deployments\GetDeploymentRequest;
use App\Client\Resources\Deployments\GetQueuedDeploymentRequest;
use App\Client\Resources\Deployments\InitiateDeploymentRequest;
use App\Client\Resources\Deployments\ListDeploymentsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\UpdateEnvironmentRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
use App\Commands\NewApplication;
use App\ConfigRepository;
use App\Git;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    Sleep::fake();
    Http::fake(['*' => Http::response('ok', 200)]);

    $this->clonePath = sys_get_temp_dir().'/cloud-new-'.uniqid();

    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(false)->byDefault();
    $this->mockGit->shouldReceive('cloneGitHubRepository')
        ->andReturnUsing(function (string $repository, string $path) {
            File::ensureDirectoryExists($path);

            return new FakeProcessResult(exitCode: 0);
        })
        ->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    $this->sentBody = null;
    $this->sent = [];

    $application = [
        'data' => createApplicationResponse([
            'attributes' => ['repository' => ['full_name' => 'user/new-app', 'default_branch' => 'main']],
        ]),
        'included' => [organizationResponse()['data'], createEnvironmentResponse()],
    ];

    $deployment = newApplicationDeployment(...);

    $record = fn (string $name, $response) => function (PendingRequest $request) use ($name, $response) {
        $this->sent[] = $name;

        return $response instanceof Closure ? $response($request) : $response;
    };

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make(['data' => [], 'included' => [], 'links' => ['next' => null]], 200),
        CreateApplicationRequest::class => $record('create-application', function (PendingRequest $request) use ($application) {
            $this->sentBody = $request->body()->all();

            return MockResponse::make($application, 201);
        }),
        GetApplicationRequest::class => MockResponse::make($application, 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()], 200),
        ListDeploymentsRequest::class => MockResponse::make(['data' => [$deployment('pending')['data']], 'links' => ['next' => null]], 200),
        GetDeploymentRequest::class => MockResponse::make($deployment('deployment.succeeded'), 200),
        InitiateDeploymentRequest::class => $record('redeploy', MockResponse::make($deployment('pending'), 200)),
        ListDatabaseTypesRequest::class => $record('database', MockResponse::make(versionlessDatabaseTypesResponse(), 200)),
        ListDatabasePresetsRequest::class => fn (PendingRequest $request) => MockResponse::make(databasePresetsResponse($request->query()->get('type')), 200),
        ListDatabaseClustersRequest::class => MockResponse::make(['data' => [], 'included' => [], 'links' => ['next' => null]], 200),
        CreateDatabaseClusterRequest::class => MockResponse::make(databaseClusterResponse(), 201),
        GetDatabaseClusterRequest::class => MockResponse::make(databaseClusterResponse(), 200),
        CreateDatabaseRequest::class => MockResponse::make(['data' => databaseSchemaResponse()], 201),
        UpdateEnvironmentRequest::class => $record('attach', function (PendingRequest $request) {
            $this->attachedDatabase = $request->body()->all()['database_schema_id'] ?? null;

            return MockResponse::make(['data' => createEnvironmentResponse()], 200);
        }),
    ]);
});

afterEach(function () {
    MockClient::destroyGlobal();
    File::deleteDirectory($this->clonePath);
});

function newApplicationDeployment(string $status): array
{
    return [
        'data' => [
            'id' => 'deploy-1',
            'type' => 'deployments',
            'attributes' => ['status' => $status, 'started_at' => now()->toISOString(), 'finished_at' => now()->toISOString()],
        ],
    ];
}

function runNew(array $options = []): int
{
    return Artisan::call('new', array_merge([
        '--starter-kit' => 'laravel-react',
        '--repository' => 'user/new-app',
        '--region' => 'us-east-1',
        '--path' => test()->clonePath,
        '--no-interaction' => true,
    ], $options));
}

it('creates the application from the starter kit on GitHub', function () {
    expect(runNew())->toBe(0)
        ->and($this->sentBody)->toMatchArray([
            'repository' => 'user/new-app',
            'name' => 'new-app',
            'starter_kit' => 'laravel-react',
            'source_control_provider_type' => 'github',
        ]);
});

it('attaches a database to a Laravel kit and redeploys so it picks up the credentials', function () {
    runNew();

    expect($this->sent)->toBe(['create-application', 'database', 'attach', 'redeploy'])
        ->and($this->attachedDatabase)->toBe(databaseSchemaResponse()['id']);
});

it('waits for a queued redeploy to start and then watches it', function () {
    $polls = 0;

    MockClient::global()->addResponses([
        InitiateDeploymentRequest::class => MockResponse::make(createQueuedDeploymentResponse('pending'), 202),
        GetQueuedDeploymentRequest::class => function () use (&$polls) {
            return MockResponse::make($polls++ === 0
                ? createQueuedDeploymentResponse('pending')
                : createQueuedDeploymentResponse('promoted', deployment: newApplicationDeployment('pending')['data']), 200);
        },
    ]);

    expect(runNew())->toBe(0)
        ->and($polls)->toBe(2);

    expect(json_decode(collect(explode(PHP_EOL, trim(Artisan::output())))->last(), true))->toMatchArray([
        'deployment_status' => 'deployment.succeeded',
    ]);
});

it('fails with the reason when a queued redeploy never starts', function () {
    MockClient::global()->addResponses([
        InitiateDeploymentRequest::class => MockResponse::make(createQueuedDeploymentResponse('pending'), 202),
        GetQueuedDeploymentRequest::class => MockResponse::make(createQueuedDeploymentResponse('failed', 'The database failed to provision.'), 200),
    ]);

    expect(runNew())->toBe(1);

    expect(json_decode(collect(explode(PHP_EOL, trim(Artisan::output())))->last(), true))->toMatchArray([
        'deployment_id' => null,
        'failure_reason' => 'The database failed to provision.',
    ]);
});

it('waits for Cloud to start the first deployment', function () {
    $lists = 0;

    MockClient::global()->addResponses([
        ListDeploymentsRequest::class => function () use (&$lists) {
            return MockResponse::make([
                'data' => $lists++ < 2 ? [] : [newApplicationDeployment('pending')['data']],
                'links' => ['next' => null],
            ], 200);
        },
    ]);

    expect(runNew(['--starter-kit' => 'go']))->toBe(0)
        ->and($lists)->toBe(3);
});

it('gives up on the first deployment when Cloud never starts one', function () {
    MockClient::global()->addResponses([
        ListDeploymentsRequest::class => MockResponse::make(['data' => [], 'links' => ['next' => null]], 200),
    ]);

    expect(runNew(['--starter-kit' => 'go']))->toBe(1);
});

it('skips the database for kits outside Laravel', function () {
    runNew(['--starter-kit' => 'nextjs']);

    expect($this->sent)->toBe(['create-application']);
});

it('skips the database when asked to', function () {
    runNew(['--database' => 'none']);

    expect($this->sent)->toBe(['create-application']);
});

it('provisions a database for other kits when asked to', function () {
    runNew(['--starter-kit' => 'rails', '--database' => 'postgres']);

    expect($this->sent)->toBe(['create-application', 'database', 'attach', 'redeploy']);
});

it('clones the repository and points it at the new application', function () {
    $this->mockGit->shouldReceive('cloneGitHubRepository')
        ->once()
        ->with('user/new-app', $this->clonePath)
        ->andReturnUsing(function (string $repository, string $path) {
            File::ensureDirectoryExists($path);

            return new FakeProcessResult(exitCode: 0);
        });

    runNew();

    expect(File::json($this->clonePath.'/.cloud/config.json'))->toBe([
        'organization_id' => 'org-1',
        'application_id' => 'app-123',
    ]);
});

it('reports the result as JSON', function () {
    runNew(['--starter-kit' => 'go']);

    expect(json_decode(collect(explode(PHP_EOL, trim(Artisan::output())))->last(), true))->toMatchArray([
        'application_id' => 'app-123',
        'environment_id' => 'env-1',
        'deployment_status' => 'deployment.succeeded',
        'repository' => 'user/new-app',
        'path' => $this->clonePath,
    ]);
});

it('refuses a clone path that already has files before creating anything', function () {
    File::ensureDirectoryExists($this->clonePath);
    File::put($this->clonePath.'/README.md', 'taken');

    expect(runNew())->toBe(1)
        ->and($this->sent)->toBe([]);
});

it('requires a starter kit when not interactive', function () {
    expect(runNew(['--starter-kit' => null]))->toBe(1)
        ->and($this->sent)->toBe([]);
});

it('rejects an unknown starter kit', function () {
    expect(runNew(['--starter-kit' => 'symfony']))->toBe(1)
        ->and($this->sent)->toBe([]);
});

it('fails when the clone fails', function () {
    $this->mockGit->shouldReceive('cloneGitHubRepository')->andReturn(
        new FakeProcessResult(exitCode: 128, errorOutput: 'Repository not found.'),
    );

    expect(runNew(['--starter-kit' => 'go']))->toBe(1);
});

function newApplicationOwners(array $repositories): array
{
    MockClient::destroyGlobal();
    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => collect($repositories)->map(fn (string $repository, int $i) => createApplicationResponse([
                'id' => 'app-'.$i,
                'attributes' => ['repository' => ['full_name' => $repository, 'default_branch' => 'main']],
            ]))->all(),
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $command = app(NewApplication::class);

    return (function () {
        $this->client = new Connector('test-api-token');

        return $this->repositoryOwners()->all();
    })->call($command);
}

it('suggests owners from existing applications first, most used first, then from the GitHub CLI', function () {
    Process::fake([
        '*user/orgs*' => Process::result(json_encode([['login' => 'acme'], ['login' => 'side-org']])),
        '*api*user*' => Process::result(json_encode(['login' => 'me'])),
        '*' => Process::result(),
    ]);

    expect(newApplicationOwners(['acme/one', 'laravel/two', 'acme/three']))->toBe(['acme', 'laravel', 'me', 'side-org']);
});

it('suggests owners from existing applications alone when the GitHub CLI is not signed in', function () {
    Process::fake([
        '*' => Process::result(exitCode: 1),
    ]);

    expect(newApplicationOwners(['laravel/two']))->toBe(['laravel']);
});

it('drops the first deployment warning from the result once the redeploy succeeds', function () {
    MockClient::global()->addResponses([
        ListDeploymentsRequest::class => MockResponse::make(['data' => [], 'links' => ['next' => null]], 200),
    ]);

    expect(runNew())->toBe(0);

    expect(json_decode(collect(explode(PHP_EOL, trim(Artisan::output())))->last(), true))->toMatchArray([
        'deployment_status' => 'deployment.succeeded',
        'failure_reason' => null,
    ]);
});
