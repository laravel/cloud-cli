<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\Client\Resources\Instances\GetInstanceRequest;
use App\Client\Resources\Instances\ListInstancesRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
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

function instanceResponseData(): array
{
    return [
        'id' => 'inst-123',
        'type' => 'instances',
        'attributes' => [
            'name' => 'web',
            'type' => 'web',
            'size' => 'compute-optimized-512',
            'scaling_type' => 'fixed',
            'min_replicas' => 1,
            'max_replicas' => 1,
            'uses_scheduler' => false,
            'scaling_cpu_threshold_percentage' => 80,
            'scaling_memory_threshold_percentage' => 80,
            'created_at' => '2025-01-01T00:00:00.000000Z',
            'updated_at' => '2025-01-01T00:00:00.000000Z',
        ],
        'relationships' => [
            'environment' => ['data' => ['id' => 'env-1', 'type' => 'environments']],
            'backgroundProcesses' => ['data' => []],
        ],
    ];
}

it('gets an instance by ID', function () {
    Prompt::fake();

    MockClient::global([
        GetInstanceRequest::class => MockResponse::make([
            'data' => instanceResponseData(),
            'included' => [createEnvironmentResponse()],
        ], 200),
    ]);

    $this->artisan('instance:get', ['instance' => 'inst-123'])
        ->assertSuccessful();
});

it('gets an instance by resolving from environment when no ID given', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
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
        ListInstancesRequest::class => MockResponse::make([
            'data' => [instanceResponseData()],
            'included' => [createEnvironmentResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('instance:get')
        ->assertSuccessful();
});
