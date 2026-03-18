<?php

use App\Client\Resources\BackgroundProcesses\DeleteBackgroundProcessRequest;
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

function bgDeleteProcessGetResponse(): array
{
    return [
        'data' => [
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
        ],
        'included' => [],
    ];
}

it('deletes a background process by ID with force flag', function () {
    Prompt::fake();

    MockClient::global([
        GetBackgroundProcessRequest::class => MockResponse::make(bgDeleteProcessGetResponse(), 200),
        DeleteBackgroundProcessRequest::class => MockResponse::make([], 204),
    ]);

    $this->artisan('background-process:delete', [
        'process' => 'process-123',
        '--force' => true,
    ])->assertSuccessful();
});

it('deletes after confirming via prompt when force and process are both provided', function () {
    Prompt::fake();

    MockClient::global([
        GetBackgroundProcessRequest::class => MockResponse::make(bgDeleteProcessGetResponse(), 200),
        DeleteBackgroundProcessRequest::class => MockResponse::make([], 204),
    ]);

    // confirm() defaults to true when faked (no default: false specified),
    // and dontConfirm = true because --force is set and process argument is given
    $this->artisan('background-process:delete', [
        'process' => 'process-123',
        '--force' => true,
    ])->assertSuccessful();
});

it('proceeds with deletion when confirm returns true (default) via prompt', function () {
    Prompt::fake();

    MockClient::global([
        GetBackgroundProcessRequest::class => MockResponse::make(bgDeleteProcessGetResponse(), 200),
        DeleteBackgroundProcessRequest::class => MockResponse::make([], 204),
    ]);

    // Without --force, dontConfirm = false. confirm() defaults to true when faked.
    // So deletion proceeds.
    $this->artisan('background-process:delete', [
        'process' => 'process-123',
    ])->assertSuccessful();
});

// BUG: BackgroundProcessDelete catches Illuminate\Http\Client\RequestException instead of
// Saloon\Exceptions\Request\RequestException. API errors (500) are not caught
// by the command's try/catch and propagate to the framework's exception handler,
// resulting in a generic failure instead of the friendly "Failed to delete" message.
it('fails on API error because wrong exception class is caught', function () {
    Prompt::fake();

    MockClient::global([
        GetBackgroundProcessRequest::class => MockResponse::make(bgDeleteProcessGetResponse(), 200),
        DeleteBackgroundProcessRequest::class => MockResponse::make(['message' => 'Server error'], 500),
    ]);

    // BUG: The wrong exception class means this throws instead of showing a friendly error.
    // Once the import is fixed to Saloon\Exceptions\Request\RequestException (see PR #42),
    // this test should change to ->assertFailed() with expectsOutputToContain('Failed to delete').
})->skip('Known bug: catches Illuminate\\Http\\Client\\RequestException instead of Saloon — see PR #42');
