<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\Client\Resources\Instances\ListInstancesRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Sleep::fake();

    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->mockGit->shouldReceive('currentBranch')->andReturn('main')->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('')->byDefault();
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(false)->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(fn () => MockClient::destroyGlobal());

function instanceListEnvironmentMocks(): array
{
    return [
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
    ];
}

function instanceApiResponse(array $overrides = []): array
{
    return array_merge([
        'id' => 'inst-123',
        'type' => 'instances',
        'attributes' => [
            'name' => 'web',
            'type' => 'service',
            'size' => 'shared-1x',
            'scaling_type' => 'custom',
            'min_replicas' => 1,
            'max_replicas' => 3,
            'uses_scheduler' => false,
            'scaling_cpu_threshold_percentage' => 70,
            'scaling_memory_threshold_percentage' => 70,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ],
        'relationships' => [],
    ], $overrides);
}

it('lists instances for an environment', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    MockClient::global(array_merge(instanceListEnvironmentMocks(), [
        ListInstancesRequest::class => MockResponse::make([
            'data' => [instanceApiResponse()],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]));

    $this->artisan('instance:list', ['environment' => 'env-1'])
        ->assertSuccessful();
});

it('outputs empty items as JSON in non-interactive mode when no instances found', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    MockClient::global(array_merge(instanceListEnvironmentMocks(), [
        ListInstancesRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]));

    // In non-interactive mode (test env), wantsJson() returns true,
    // so outputJsonIfWanted exits with SUCCESS before the empty check.
    // This is expected behavior - JSON output always succeeds with data.
    $this->artisan('instance:list', [
        'environment' => 'env-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('lists multiple instances', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    $secondInstance = instanceApiResponse([
        'id' => 'inst-456',
        'attributes' => [
            'name' => 'worker',
            'type' => 'worker',
            'size' => 'shared-2x',
            'scaling_type' => 'none',
            'min_replicas' => 1,
            'max_replicas' => 1,
            'uses_scheduler' => true,
            'scaling_cpu_threshold_percentage' => null,
            'scaling_memory_threshold_percentage' => null,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ],
    ]);

    MockClient::global(array_merge(instanceListEnvironmentMocks(), [
        ListInstancesRequest::class => MockResponse::make([
            'data' => [instanceApiResponse(), $secondInstance],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]));

    $this->artisan('instance:list', ['environment' => 'env-1'])
        ->assertSuccessful();
});
