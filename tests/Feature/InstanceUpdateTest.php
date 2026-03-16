<?php

use App\Client\Resources\Instances\GetInstanceRequest;
use App\Client\Resources\Instances\ListInstanceSizesRequest;
use App\Client\Resources\Instances\UpdateInstanceRequest;
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

function instanceUpdateGetMock(array $overrides = []): array
{
    return [
        'data' => [
            'id' => 'inst-123',
            'type' => 'instances',
            'attributes' => array_merge([
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
            ], $overrides['attributes'] ?? []),
            'relationships' => [
                'environment' => ['data' => ['id' => 'env-1', 'type' => 'environments']],
            ],
        ],
        'included' => [
            [
                'id' => 'env-1',
                'type' => 'environments',
                'attributes' => [
                    'name' => 'production',
                    'slug' => 'production',
                    'vanity_domain' => 'my-app.cloud.laravel.com',
                    'status' => 'running',
                    'php_major_version' => '8.3',
                    'uses_octane' => false,
                    'uses_hibernation' => false,
                ],
            ],
        ],
    ];
}

function instanceSizesForUpdateResponse(): array
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
                [
                    'name' => 'shared-2x',
                    'label' => 'Shared 2x',
                    'description' => '0.5 vCPU, 512 MiB',
                    'cpu_type' => 'shared',
                    'compute_class' => 'shared',
                    'cpu_count' => 2,
                    'memory_mib' => 512,
                ],
            ],
        ],
    ];
}

it('updates an instance with options and force flag in non-interactive mode', function () {
    Prompt::fake();

    $getCallCount = 0;
    MockClient::global([
        GetInstanceRequest::class => function () use (&$getCallCount) {
            $getCallCount++;
            if ($getCallCount === 1) {
                return MockResponse::make(instanceUpdateGetMock(), 200);
            }

            return MockResponse::make(instanceUpdateGetMock(['attributes' => ['size' => 'shared-2x']]), 200);
        },
        ListInstanceSizesRequest::class => MockResponse::make(instanceSizesForUpdateResponse(), 200),
        UpdateInstanceRequest::class => MockResponse::make(instanceUpdateGetMock(['attributes' => ['size' => 'shared-2x']]), 200),
    ]);

    $this->artisan('instance:update', [
        'instance' => 'inst-123',
        '--size' => 'shared-2x',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fails when no fields are provided in non-interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        GetInstanceRequest::class => MockResponse::make(instanceUpdateGetMock(), 200),
        ListInstanceSizesRequest::class => MockResponse::make(instanceSizesForUpdateResponse(), 200),
    ]);

    $this->artisan('instance:update', [
        'instance' => 'inst-123',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('updates multiple fields on an instance', function () {
    Prompt::fake();

    $getCallCount = 0;
    MockClient::global([
        GetInstanceRequest::class => function () use (&$getCallCount) {
            $getCallCount++;
            if ($getCallCount === 1) {
                return MockResponse::make(instanceUpdateGetMock(), 200);
            }

            return MockResponse::make(instanceUpdateGetMock(['attributes' => [
                'min_replicas' => 2,
                'max_replicas' => 5,
            ]]), 200);
        },
        ListInstanceSizesRequest::class => MockResponse::make(instanceSizesForUpdateResponse(), 200),
        UpdateInstanceRequest::class => MockResponse::make(instanceUpdateGetMock(['attributes' => [
            'min_replicas' => 2,
            'max_replicas' => 5,
        ]]), 200),
    ]);

    $this->artisan('instance:update', [
        'instance' => 'inst-123',
        '--min-replicas' => 2,
        '--max-replicas' => 5,
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});
