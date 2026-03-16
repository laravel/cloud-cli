<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\Client\Resources\Instances\CreateInstanceRequest;
use App\Client\Resources\Instances\ListInstanceSizesRequest;
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

function instanceCreateEnvironmentMocks(): array
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

function instanceSizesResponse(): array
{
    return [
        'data' => [
            'shared' => [
                [
                    'name' => 'shared-1x',
                    'label' => 'Shared 1x',
                    'description' => '0.25 vCPU, 256 MiB',
                    'cpu_type' => 'shared',
                    'compute_class' => 'shared',
                    'cpu_count' => 1,
                    'memory_mib' => 256,
                ],
            ],
        ],
    ];
}

function createdInstanceResponse(): array
{
    return [
        'data' => [
            'id' => 'inst-new',
            'type' => 'instances',
            'attributes' => [
                'name' => 'my-instance',
                'type' => 'service',
                'size' => 'shared-1x',
                'scaling_type' => 'custom',
                'min_replicas' => 1,
                'max_replicas' => 3,
                'uses_scheduler' => false,
                'scaling_cpu_threshold_percentage' => 50,
                'scaling_memory_threshold_percentage' => 50,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
            'relationships' => [],
        ],
        'included' => [],
    ];
}

// InstanceCreate requires interactive mode for several fields (scaling_type, uses_scheduler)
// that have no CLI option equivalents. Non-interactive mode fails because these required
// fields cannot be provided via options.
it('fails in non-interactive mode when required interactive-only fields are missing', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    MockClient::global(array_merge(instanceCreateEnvironmentMocks(), [
        ListInstanceSizesRequest::class => MockResponse::make(instanceSizesResponse(), 200),
    ]));

    // scaling_type has no CLI option, so non-interactive mode throws RuntimeException
    // which BaseCommand::run() catches and returns FAILURE
    $this->artisan('instance:create', [
        'environment' => 'env-1',
        '--name' => 'my-instance',
        '--size' => 'shared-1x',
        '--min-replicas' => 1,
        '--max-replicas' => 3,
        '--no-interaction' => true,
    ])->assertFailed();
});

it('handles validation errors on instance create in non-interactive mode', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    MockClient::global(array_merge(instanceCreateEnvironmentMocks(), [
        ListInstanceSizesRequest::class => MockResponse::make(instanceSizesResponse(), 200),
        CreateInstanceRequest::class => MockResponse::make([
            'message' => 'Validation failed',
            'errors' => ['name' => ['The name has already been taken.']],
        ], 422),
    ]));

    $this->artisan('instance:create', [
        'environment' => 'env-1',
        '--name' => 'duplicate',
        '--size' => 'shared-1x',
        '--no-interaction' => true,
    ])->assertFailed();
});
