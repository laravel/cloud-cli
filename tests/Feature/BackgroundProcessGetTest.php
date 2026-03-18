<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\BackgroundProcesses\GetBackgroundProcessRequest;
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

function bgGetProcessResponse(array $overrides = []): array
{
    return array_merge_recursive([
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
                'strategy_type' => null,
                'strategy_threshold' => null,
                'created_at' => now()->toISOString(),
            ],
            'relationships' => [
                'instance' => ['data' => ['id' => 'inst-123', 'type' => 'instances']],
            ],
        ],
        'included' => [],
    ], $overrides);
}

// ---- Get by ID ----

it('gets background process by ID successfully', function () {
    Prompt::fake();

    MockClient::global([
        GetBackgroundProcessRequest::class => MockResponse::make(bgGetProcessResponse(), 200),
    ]);

    $this->artisan('background-process:get', [
        'process' => 'process-123',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('gets background process by ID with --json output', function () {
    MockClient::global([
        GetBackgroundProcessRequest::class => MockResponse::make(bgGetProcessResponse(), 200),
    ]);

    $this->artisan('background-process:get', [
        'process' => 'process-123',
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"id"');
});

// ---- Custom type process ----

it('gets custom type background process', function () {
    Prompt::fake();

    MockClient::global([
        GetBackgroundProcessRequest::class => MockResponse::make([
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
        ], 200),
    ]);

    $this->artisan('background-process:get', [
        'process' => 'process-456',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

// ---- Not found ----

it('fails when background process not found by ID and no apps exist', function () {
    Prompt::fake();

    // When the ID lookup fails (404), resolver falls back to fromInput() which resolves
    // the application -> instance chain. With no apps, it fails.
    MockClient::global([
        GetBackgroundProcessRequest::class => MockResponse::make(['message' => 'Not found'], 404),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('background-process:get', [
        'process' => 'process-nonexistent',
        '--no-interaction' => true,
    ])->assertFailed();
});

// ---- No argument in non-interactive mode ----

it('fails when no process argument given and no apps exist in non-interactive mode', function () {
    Prompt::fake();

    // Without a process argument, the resolver tries fromInput which resolves
    // application -> instance -> background process. With no apps, it fails.
    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('background-process:get', [
        '--no-interaction' => true,
    ])->assertFailed();
});
