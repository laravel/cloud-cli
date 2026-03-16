<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\BackgroundProcesses\GetBackgroundProcessRequest;
use App\Client\Resources\BackgroundProcesses\UpdateBackgroundProcessRequest;
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

function bgUpdateProcessResponse(array $overrides = []): array
{
    $base = [
        'data' => [
            'id' => 'process-123',
            'type' => 'backgroundProcesses',
            'attributes' => [
                'type' => 'worker',
                'processes' => 2,
                'command' => 'php artisan queue:work',
                'config' => [
                    'connection' => 'database',
                    'queue' => 'default',
                    'tries' => 3,
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

    if (isset($overrides['attributes'])) {
        $base['data']['attributes'] = array_merge($base['data']['attributes'], $overrides['attributes']);
    }

    return $base;
}

// ---- Update worker process with --force ----

it('updates background process processes count with --force', function () {
    Prompt::fake();

    MockClient::global([
        GetBackgroundProcessRequest::class => MockResponse::make(bgUpdateProcessResponse(), 200),
        UpdateBackgroundProcessRequest::class => MockResponse::make(
            bgUpdateProcessResponse(['attributes' => ['processes' => 5]]),
            200,
        ),
    ]);

    $this->artisan('background-process:update', [
        'process' => 'process-123',
        '--processes' => 5,
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('updates background process with --json output', function () {
    MockClient::global([
        GetBackgroundProcessRequest::class => MockResponse::make(bgUpdateProcessResponse(), 200),
        UpdateBackgroundProcessRequest::class => MockResponse::make(
            bgUpdateProcessResponse(['attributes' => ['processes' => 3]]),
            200,
        ),
    ]);

    $this->artisan('background-process:update', [
        'process' => 'process-123',
        '--processes' => 3,
        '--force' => true,
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"id"');
});

// ---- Update worker config fields ----

it('updates worker connection and queue with --force', function () {
    Prompt::fake();

    MockClient::global([
        GetBackgroundProcessRequest::class => MockResponse::make(bgUpdateProcessResponse(), 200),
        UpdateBackgroundProcessRequest::class => MockResponse::make(
            bgUpdateProcessResponse(['attributes' => ['config' => [
                'connection' => 'redis',
                'queue' => 'high,default',
                'tries' => 3,
                'backoff' => 30,
                'sleep' => 10,
                'rest' => 0,
                'timeout' => 60,
                'force' => false,
            ]]]),
            200,
        ),
    ]);

    $this->artisan('background-process:update', [
        'process' => 'process-123',
        '--connection' => 'redis',
        '--queue' => 'high,default',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('updates worker timeout and tries with --force', function () {
    Prompt::fake();

    MockClient::global([
        GetBackgroundProcessRequest::class => MockResponse::make(bgUpdateProcessResponse(), 200),
        UpdateBackgroundProcessRequest::class => MockResponse::make(
            bgUpdateProcessResponse(['attributes' => ['config' => [
                'connection' => 'database',
                'queue' => 'default',
                'tries' => 5,
                'backoff' => 30,
                'sleep' => 10,
                'rest' => 0,
                'timeout' => 120,
                'force' => false,
            ]]]),
            200,
        ),
    ]);

    $this->artisan('background-process:update', [
        'process' => 'process-123',
        '--tries' => 5,
        '--timeout' => 120,
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

// ---- Update custom process ----

it('updates custom background process command with --force', function () {
    Prompt::fake();

    $customProcess = [
        'data' => [
            'id' => 'process-456',
            'type' => 'backgroundProcesses',
            'attributes' => [
                'type' => 'custom',
                'processes' => 1,
                'command' => 'php artisan horizon',
                'config' => null,
                'created_at' => now()->toISOString(),
            ],
            'relationships' => [
                'instance' => ['data' => ['id' => 'inst-123', 'type' => 'instances']],
            ],
        ],
        'included' => [],
    ];

    $updatedCustomProcess = $customProcess;
    $updatedCustomProcess['data']['attributes']['command'] = 'php artisan horizon:work';

    MockClient::global([
        GetBackgroundProcessRequest::class => MockResponse::make($customProcess, 200),
        UpdateBackgroundProcessRequest::class => MockResponse::make($updatedCustomProcess, 200),
    ]);

    $this->artisan('background-process:update', [
        'process' => 'process-456',
        '--command' => 'php artisan horizon:work',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

// ---- No fields to update ----

it('fails when no fields provided in non-interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        GetBackgroundProcessRequest::class => MockResponse::make(bgUpdateProcessResponse(), 200),
    ]);

    $this->artisan('background-process:update', [
        'process' => 'process-123',
        '--no-interaction' => true,
    ])->assertFailed();
});

// ---- Not found ----

it('fails when background process not found', function () {
    Prompt::fake();

    // When process-ID lookup fails (404), resolver falls back to fromInput() which
    // resolves application -> instance. With no apps, it fails.
    MockClient::global([
        GetBackgroundProcessRequest::class => MockResponse::make(['message' => 'Not found'], 404),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('background-process:update', [
        'process' => 'process-nonexistent',
        '--processes' => 3,
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();
});
