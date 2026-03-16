<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\BackgroundProcesses\CreateBackgroundProcessRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\Client\Resources\Instances\GetInstanceRequest;
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

function bgCreateInstanceGetMock(): array
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

function bgCreateProcessResponse(): array
{
    return [
        'data' => [
            'id' => 'process-new',
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
        ],
        'included' => [],
    ];
}

it('creates a worker background process with default options in non-interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        GetInstanceRequest::class => MockResponse::make(bgCreateInstanceGetMock(), 200),
        CreateBackgroundProcessRequest::class => MockResponse::make(bgCreateProcessResponse(), 200),
    ]);

    $this->artisan('background-process:create', [
        'instance' => 'inst-123',
        '--type' => 'worker',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('creates a custom background process in non-interactive mode', function () {
    Prompt::fake();

    $customResponse = bgCreateProcessResponse();
    $customResponse['data']['attributes']['type'] = 'custom';
    $customResponse['data']['attributes']['command'] = 'php artisan horizon';
    $customResponse['data']['attributes']['config'] = null;

    MockClient::global([
        GetInstanceRequest::class => MockResponse::make(bgCreateInstanceGetMock(), 200),
        CreateBackgroundProcessRequest::class => MockResponse::make($customResponse, 200),
    ]);

    $this->artisan('background-process:create', [
        'instance' => 'inst-123',
        '--type' => 'custom',
        '--command' => 'php artisan horizon',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('handles validation errors on background process create', function () {
    Prompt::fake();

    MockClient::global([
        GetInstanceRequest::class => MockResponse::make(bgCreateInstanceGetMock(), 200),
        CreateBackgroundProcessRequest::class => MockResponse::make([
            'message' => 'Validation failed',
            'errors' => ['type' => ['The type field is required.']],
        ], 422),
    ]);

    $this->artisan('background-process:create', [
        'instance' => 'inst-123',
        '--type' => 'worker',
        '--no-interaction' => true,
    ])->assertFailed();
});
