<?php

use App\Client\Resources\BackgroundProcesses\ListBackgroundProcessesRequest;
use App\Client\Resources\Instances\GetInstanceRequest;
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

function bgProcessInstanceGetMock(): array
{
    return [
        'data' => [
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
            'relationships' => [
                'environment' => ['data' => ['id' => 'env-1', 'type' => 'environments']],
            ],
        ],
        'included' => [
            createEnvironmentResponse(),
        ],
    ];
}

function bgProcessApiResponse(array $overrides = []): array
{
    return array_merge([
        'id' => 'process-123',
        'type' => 'backgroundProcesses',
        'attributes' => [
            'type' => 'worker',
            'processes' => 1,
            'command' => 'php artisan queue:work',
            'config' => [
                'connection' => 'database',
                'queue' => 'default',
                'tries' => 1,
                'backoff' => 30,
                'sleep' => 10,
                'rest' => 0,
                'timeout' => 60,
                'force' => false,
            ],
            'created_at' => now()->toISOString(),
        ],
        'relationships' => [
            'instance' => ['data' => ['id' => 'inst-123', 'type' => 'instances']],
        ],
    ], $overrides);
}

it('lists background processes for an instance by ID', function () {
    Prompt::fake();

    MockClient::global([
        GetInstanceRequest::class => MockResponse::make(bgProcessInstanceGetMock(), 200),
        ListBackgroundProcessesRequest::class => MockResponse::make([
            'data' => [bgProcessApiResponse()],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('background-process:list', [
        'instance' => 'inst-123',
    ])->assertSuccessful();
});

it('outputs empty items as JSON in non-interactive mode when no processes found', function () {
    Prompt::fake();

    MockClient::global([
        GetInstanceRequest::class => MockResponse::make(bgProcessInstanceGetMock(), 200),
        ListBackgroundProcessesRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    // In non-interactive mode (test env), wantsJson() returns true,
    // so outputJsonIfWanted exits with SUCCESS before the empty check.
    $this->artisan('background-process:list', [
        'instance' => 'inst-123',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('lists multiple background processes', function () {
    Prompt::fake();

    MockClient::global([
        GetInstanceRequest::class => MockResponse::make(bgProcessInstanceGetMock(), 200),
        ListBackgroundProcessesRequest::class => MockResponse::make([
            'data' => [
                bgProcessApiResponse(),
                bgProcessApiResponse([
                    'id' => 'process-456',
                    'attributes' => [
                        'type' => 'custom',
                        'processes' => 2,
                        'command' => 'php artisan horizon',
                        'config' => null,
                        'created_at' => now()->toISOString(),
                    ],
                ]),
            ],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('background-process:list', [
        'instance' => 'inst-123',
    ])->assertSuccessful();
});
