<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Caches\ListCachesRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\Deployments\ListDeploymentsRequest;
use App\ConfigRepository;
use App\Git;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

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

function setupStatusMocks(array $overrides = []): void
{
    $defaults = [
        'deployments' => [
            [
                'id' => 'deploy-1',
                'type' => 'deployments',
                'attributes' => [
                    'status' => 'deployment.succeeded',
                    'started_at' => now()->subHours(2)->toISOString(),
                    'finished_at' => now()->subHours(2)->addMinutes(3)->toISOString(),
                    'branch_name' => 'main',
                    'commit' => ['hash' => 'abc1234', 'message' => 'fix bug', 'author' => 'dev'],
                ],
            ],
        ],
        'databases' => [
            [
                'id' => 'db-1',
                'type' => 'databaseClusters',
                'attributes' => [
                    'name' => 'my-app-db',
                    'type' => 'mysql',
                    'status' => 'available',
                    'region' => 'us-east-1',
                    'config' => [],
                    'connection' => [],
                ],
            ],
        ],
        'caches' => [
            [
                'id' => 'cache-1',
                'type' => 'caches',
                'attributes' => [
                    'name' => 'my-app-cache',
                    'type' => 'redis',
                    'status' => 'available',
                    'region' => 'us-east-1',
                    'size' => '256MB',
                    'auto_upgrade_enabled' => false,
                    'is_public' => false,
                ],
            ],
        ],
    ];

    $data = array_merge($defaults, $overrides);

    MockClient::global([
        GetApplicationRequest::class => MockResponse::make([
            'data' => createApplicationResponse(),
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
                createEnvironmentResponse(),
            ],
        ], 200),

        ListApplicationsRequest::class => MockResponse::make([
            'data' => [createApplicationResponse()],
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
                createEnvironmentResponse(),
            ],
            'links' => ['next' => null],
        ], 200),

        ListDeploymentsRequest::class => MockResponse::make([
            'data' => $data['deployments'],
            'links' => ['next' => null],
        ], 200),

        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => $data['databases'],
            'links' => ['next' => null],
        ], 200),

        ListCachesRequest::class => MockResponse::make([
            'data' => $data['caches'],
            'links' => ['next' => null],
        ], 200),
    ]);
}

it('displays application status overview', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupStatusMocks();

    $this->artisan('status', ['application' => 'app-123'])
        ->assertSuccessful();
});

it('displays status with no databases or caches', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupStatusMocks([
        'databases' => [],
        'caches' => [],
    ]);

    $this->artisan('status', ['application' => 'app-123'])
        ->assertSuccessful();
});

it('displays status with no deployments', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupStatusMocks([
        'deployments' => [],
    ]);

    $this->artisan('status', ['application' => 'app-123'])
        ->assertSuccessful();
});

it('outputs json when json flag is provided', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupStatusMocks();

    $this->artisan('status', ['application' => 'app-123', '--json' => true])
        ->assertSuccessful();
});
