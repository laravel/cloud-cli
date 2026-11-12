<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Deployments\GetDeploymentRequest;
use App\Client\Resources\Deployments\GetQueuedDeploymentRequest;
use App\Client\Resources\Deployments\InitiateDeploymentRequest;
use App\Client\Resources\Deployments\ListDeploymentsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    Sleep::fake();

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

function createDeploymentResponse(string $status = 'pending', array $overrides = []): array
{
    return [
        'data' => array_merge([
            'id' => 'deploy-123',
            'type' => 'deployments',
            'attributes' => [
                'status' => $status,
                'started_at' => now()->toISOString(),
                'finished_at' => $status === 'deployment.succeeded' || $status === 'deployment.failed'
                    ? now()->toISOString()
                    : null,
            ],
        ], $overrides),
    ];
}

function setupSuccessfulDeployMocks(): void
{
    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [createApplicationResponse()],
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org']],
                createEnvironmentResponse(),
            ],
            'links' => ['next' => null],
        ], 200),

        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [createEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),

        GetEnvironmentRequest::class => MockResponse::make([
            'data' => createEnvironmentResponse(),
        ], 200),

        InitiateDeploymentRequest::class => MockResponse::make(
            createDeploymentResponse('pending'),
            200,
        ),

        GetDeploymentRequest::class => MockResponse::make(
            createDeploymentResponse('deployment.succeeded'),
            200,
        ),
    ]);
}

it('deploys an application successfully when one app and one environment exist', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupSuccessfulDeployMocks();

    $this->artisan('deploy')
        ->assertSuccessful();
});

it('deploys using explicit application and environment arguments', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupSuccessfulDeployMocks();

    $this->artisan('deploy', [
        'application' => 'My App',
        'environment' => 'production',
    ])->assertSuccessful();
});

it('selects application when given by name argument with multiple apps', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [
                [
                    'id' => 'app-123',
                    'type' => 'applications',
                    'attributes' => [
                        'name' => 'My App Production',
                        'slug' => 'my-app-prod',
                        'region' => 'us-east-1',
                        'repository' => ['full_name' => 'user/my-app', 'default_branch' => 'main'],
                    ],
                    'relationships' => [
                        'organization' => ['data' => ['id' => 'org-1', 'type' => 'organizations']],
                        'environments' => ['data' => [['id' => 'env-1', 'type' => 'environments']]],
                        'defaultEnvironment' => ['data' => ['id' => 'env-1', 'type' => 'environments']],
                    ],
                ],
                [
                    'id' => 'app-456',
                    'type' => 'applications',
                    'attributes' => [
                        'name' => 'My App Staging',
                        'slug' => 'my-app-staging',
                        'region' => 'us-east-1',
                        'repository' => ['full_name' => 'user/my-app', 'default_branch' => 'main'],
                    ],
                    'relationships' => [
                        'organization' => ['data' => ['id' => 'org-1', 'type' => 'organizations']],
                        'environments' => ['data' => [['id' => 'env-2', 'type' => 'environments']]],
                        'defaultEnvironment' => ['data' => ['id' => 'env-2', 'type' => 'environments']],
                    ],
                ],
            ],
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org']],
                createEnvironmentResponse(),
                [
                    'id' => 'env-2',
                    'type' => 'environments',
                    'attributes' => [
                        'name' => 'staging',
                        'slug' => 'staging',
                        'vanity_domain' => 'my-app-staging.cloud.laravel.com',
                        'status' => 'running',
                        'php_major_version' => '8.3',
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),

        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [[
                'id' => 'env-2',
                'type' => 'environments',
                'attributes' => [
                    'name' => 'staging',
                    'slug' => 'staging',
                    'vanity_domain' => 'my-app-staging.cloud.laravel.com',
                    'status' => 'running',
                    'php_major_version' => '8.3',
                ],
            ]],
            'links' => ['next' => null],
        ], 200),

        GetEnvironmentRequest::class => MockResponse::make([
            'data' => [
                'id' => 'env-2',
                'type' => 'environments',
                'attributes' => [
                    'name' => 'staging',
                    'slug' => 'staging',
                    'vanity_domain' => 'my-app-staging.cloud.laravel.com',
                    'status' => 'running',
                    'php_major_version' => '8.3',
                ],
            ],
        ], 200),

        InitiateDeploymentRequest::class => MockResponse::make(
            createDeploymentResponse('pending'),
            200,
        ),

        GetDeploymentRequest::class => MockResponse::make(
            createDeploymentResponse('deployment.succeeded'),
            200,
        ),
    ]);

    $this->artisan('deploy', [
        'application' => 'My App Staging',
        'environment' => 'staging',
    ])->assertSuccessful();
});

it('selects environment by name when multiple environments exist', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [[
                'id' => 'app-123',
                'type' => 'applications',
                'attributes' => [
                    'name' => 'My App',
                    'slug' => 'my-app',
                    'region' => 'us-east-1',
                    'repository' => ['full_name' => 'user/my-app', 'default_branch' => 'main'],
                ],
                'relationships' => [
                    'organization' => ['data' => ['id' => 'org-1', 'type' => 'organizations']],
                    'environments' => ['data' => [
                        ['id' => 'env-1', 'type' => 'environments'],
                        ['id' => 'env-2', 'type' => 'environments'],
                    ]],
                    'defaultEnvironment' => ['data' => ['id' => 'env-1', 'type' => 'environments']],
                ],
            ]],
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org']],
                createEnvironmentResponse(),
                [
                    'id' => 'env-2',
                    'type' => 'environments',
                    'attributes' => [
                        'name' => 'staging',
                        'slug' => 'staging',
                        'vanity_domain' => 'my-app-staging.cloud.laravel.com',
                        'status' => 'running',
                        'php_major_version' => '8.3',
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),

        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [
                createEnvironmentResponse(),
                [
                    'id' => 'env-2',
                    'type' => 'environments',
                    'attributes' => [
                        'name' => 'staging',
                        'slug' => 'staging',
                        'vanity_domain' => 'my-app-staging.cloud.laravel.com',
                        'status' => 'running',
                        'php_major_version' => '8.3',
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),

        GetEnvironmentRequest::class => MockResponse::make([
            'data' => [
                'id' => 'env-2',
                'type' => 'environments',
                'attributes' => [
                    'name' => 'staging',
                    'slug' => 'staging',
                    'vanity_domain' => 'my-app-staging.cloud.laravel.com',
                    'status' => 'running',
                    'php_major_version' => '8.3',
                ],
            ],
        ], 200),

        InitiateDeploymentRequest::class => MockResponse::make(
            createDeploymentResponse('pending'),
            200,
        ),

        GetDeploymentRequest::class => MockResponse::make(
            createDeploymentResponse('deployment.succeeded'),
            200,
        ),
    ]);

    $this->artisan('deploy', [
        'application' => 'My App',
        'environment' => 'staging',
    ])->assertSuccessful();
});

it('deploys to specific application by ID', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupSuccessfulDeployMocks();

    $this->artisan('deploy', [
        'application' => 'app-123',
        'environment' => 'production',
    ])->assertSuccessful();
});

function setupFailedDeployMocks(): void
{
    setupSuccessfulDeployMocks();

    $polls = 0;

    MockClient::global()->addResponses([
        ListDeploymentsRequest::class => MockResponse::make([
            'data' => [createDeploymentResponse('build.running')['data']],
            'links' => ['next' => null],
        ], 200),

        // Throwing instead of hanging if the watcher never treats `failed` as finished.
        GetDeploymentRequest::class => function () use (&$polls) {
            if (++$polls > 20) {
                throw new RuntimeException('Deployment watcher did not stop on a failed status.');
            }

            return MockResponse::make(
                createDeploymentResponse('failed', ['attributes' => ['status' => 'failed', 'failure_reason' => 'Out of memory']]),
                200,
            );
        },
    ]);
}

it('stops watching and fails when the deployment reaches the failed status', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupFailedDeployMocks();

    $this->artisan('deploy', [
        'application' => 'My App',
        'environment' => 'production',
        '--json' => true,
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('"failure_reason":"Out of memory"')
        ->assertFailed();
});

it('stops monitoring when the deployment reaches the failed status', function () {
    $this->mockGit->shouldReceive('hasRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupFailedDeployMocks();

    $this->artisan('deploy:monitor', [
        'application' => 'My App',
        'environment' => 'production',
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('"status":"failed"')
        ->assertSuccessful();
});

/**
 * Cloud answers a deploy with 202 and a queued deployment while the environment's resources provision.
 *
 * @param  list<array>  $queuedPolls  Successive GET /queued-deployments/{id} bodies; the last one repeats.
 */
function setupQueuedDeployMocks(array $queuedPolls): void
{
    setupSuccessfulDeployMocks();

    $polls = 0;

    MockClient::global()->addResponses([
        InitiateDeploymentRequest::class => MockResponse::make(
            createQueuedDeploymentResponse('pending'),
            202,
            ['Content-Location' => 'https://cloud.laravel.com/api/queued-deployments/queued-1'],
        ),

        GetQueuedDeploymentRequest::class => function (PendingRequest $request) use (&$polls, $queuedPolls) {
            expect($request->query()->get('include'))->toContain('deployment');

            $body = $queuedPolls[min($polls++, count($queuedPolls) - 1)];

            return MockResponse::make($body, 200);
        },

        // Cloud binds /deployments/{id} to deployments only, so a queued ID 404s there.
        GetDeploymentRequest::class => fn (PendingRequest $request) => str_contains($request->getUrl(), 'queued-1')
            ? MockResponse::make(['message' => 'Not found.'], 404)
            : MockResponse::make(createDeploymentResponse('deployment.succeeded'), 200),
    ]);
}

it('waits for a queued deployment to be promoted and then watches it', function () {
    $this->mockGit->shouldReceive('hasRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupQueuedDeployMocks([
        createQueuedDeploymentResponse('pending'),
        createQueuedDeploymentResponse('promoted', deployment: createDeploymentResponse('pending')['data']),
    ]);

    $this->artisan('deploy', [
        'application' => 'My App',
        'environment' => 'production',
        '--json' => true,
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('"queued_deployment_id":"queued-1","status":"queued"')
        ->expectsOutputToContain('"deployment_id":"deploy-123","status":"initiated"')
        ->expectsOutputToContain('"status":"deployment.succeeded"')
        ->assertSuccessful();
});

it('waits for a queued deployment interactively', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupQueuedDeployMocks([
        createQueuedDeploymentResponse('promoted', deployment: createDeploymentResponse('pending')['data']),
    ]);

    $this->artisan('deploy', [
        'application' => 'My App',
        'environment' => 'production',
    ])->assertSuccessful();
});

it('fails with the reason when a queued deployment does not start', function () {
    $this->mockGit->shouldReceive('hasRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupQueuedDeployMocks([
        createQueuedDeploymentResponse('pending'),
        createQueuedDeploymentResponse('failed', 'The database failed to provision.'),
    ]);

    $this->artisan('deploy', [
        'application' => 'My App',
        'environment' => 'production',
        '--json' => true,
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('"queued_deployment_id":"queued-1","status":"failed","failure_reason":"The database failed to provision."')
        ->assertFailed();
});

it('reports a queued deployment without claiming a deployment ID when not waiting', function () {
    $this->mockGit->shouldReceive('hasRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupQueuedDeployMocks([createQueuedDeploymentResponse('pending')]);

    $this->artisan('deploy', [
        'application' => 'My App',
        'environment' => 'production',
        '--no-wait' => true,
        '--json' => true,
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('"queued_deployment_id":"queued-1","status":"queued"')
        ->doesntExpectOutputToContain('deployment_id":"queued-1')
        ->assertSuccessful();
});

it('reports the deployment ID when not waiting on a deployment that started right away', function () {
    $this->mockGit->shouldReceive('hasRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupSuccessfulDeployMocks();

    $this->artisan('deploy', [
        'application' => 'My App',
        'environment' => 'production',
        '--no-wait' => true,
        '--json' => true,
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('"deployment_id":"deploy-123","status":"pending"')
        ->assertSuccessful();
});
