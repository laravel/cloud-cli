<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Commands\GetCommandRequest;
use App\Client\Resources\Commands\RunCommandRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
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

function commandRunEnvironmentMocks(): array
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

function commandRunResponse(string $status = 'pending'): array
{
    return [
        'data' => [
            'id' => 'cmd-123',
            'type' => 'commands',
            'attributes' => [
                'command' => 'php artisan migrate',
                'status' => $status,
                'output' => $status === 'command.success' ? 'Migration complete' : null,
                'exit_code' => $status === 'command.success' ? 0 : null,
                'started_at' => now()->toISOString(),
                'finished_at' => $status === 'command.success' ? now()->toISOString() : null,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
            'relationships' => [],
        ],
        'included' => [],
    ];
}

it('runs a command on an environment with --no-monitor', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    MockClient::global(array_merge(commandRunEnvironmentMocks(), [
        RunCommandRequest::class => MockResponse::make(commandRunResponse('pending'), 200),
    ]));

    $this->artisan('command:run', [
        'environment' => 'env-1',
        '--cmd' => 'php artisan migrate',
        '--no-monitor' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('runs a command and monitors it', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    MockClient::global(array_merge(commandRunEnvironmentMocks(), [
        RunCommandRequest::class => MockResponse::make(commandRunResponse('pending'), 200),
        GetCommandRequest::class => MockResponse::make(commandRunResponse('command.success'), 200),
    ]));

    $this->artisan('command:run', [
        'environment' => 'env-1',
        '--cmd' => 'php artisan migrate',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('handles validation errors on command run', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    MockClient::global(array_merge(commandRunEnvironmentMocks(), [
        RunCommandRequest::class => MockResponse::make([
            'message' => 'Validation failed',
            'errors' => ['command' => ['The command field is required.']],
        ], 422),
    ]));

    $this->artisan('command:run', [
        'environment' => 'env-1',
        '--cmd' => '',
        '--no-monitor' => true,
        '--no-interaction' => true,
    ])->assertFailed();
});
