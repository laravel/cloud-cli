<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Caches\ListCachesRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\Deployments\ListDeploymentsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentLogsRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\ConfigRepository;
use Laravel\Prompts\Prompt;
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

function setupDebugMocks(): void
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

        ListDeploymentsRequest::class => MockResponse::make([
            'data' => [
                [
                    'id' => 'deploy-1',
                    'type' => 'deployments',
                    'attributes' => [
                        'status' => 'deployment.succeeded',
                        'commit' => ['hash' => 'abc1234567890'],
                        'started_at' => now()->subMinutes(10)->toISOString(),
                        'finished_at' => now()->subMinutes(8)->toISOString(),
                        'branch_name' => 'main',
                        'php_major_version' => '8.3',
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),

        ListEnvironmentLogsRequest::class => MockResponse::make([], 200),

        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [
                [
                    'id' => 'db-1',
                    'type' => 'databaseClusters',
                    'attributes' => [
                        'name' => 'my-db',
                        'type' => 'mysql',
                        'status' => 'running',
                        'region' => 'us-east-1',
                        'config' => [],
                        'connection' => [],
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),

        ListCachesRequest::class => MockResponse::make([
            'data' => [
                [
                    'id' => 'cache-1',
                    'type' => 'caches',
                    'attributes' => [
                        'name' => 'my-cache',
                        'type' => 'redis',
                        'status' => 'running',
                        'region' => 'us-east-1',
                        'size' => '256mb',
                        'auto_upgrade_enabled' => false,
                        'is_public' => false,
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),
    ]);
}

it('displays diagnostic information for an environment', function () {
    Prompt::fake();
    setupDebugMocks();

    $this->artisan('debug', [
        'application' => 'My App',
        'environment' => 'production',
    ])->assertSuccessful();
});

it('outputs json when --json flag is provided', function () {
    Prompt::fake();
    setupDebugMocks();

    $this->artisan('debug', [
        'application' => 'My App',
        'environment' => 'production',
        '--json' => true,
    ])->assertSuccessful();
});
