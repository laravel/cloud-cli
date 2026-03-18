<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Commands\ListCommandsRequest;
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

function commandListEnvironmentMocks(): array
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

function commandApiResponse(array $overrides = []): array
{
    return array_merge([
        'id' => 'cmd-123',
        'type' => 'commands',
        'attributes' => [
            'command' => 'php artisan migrate',
            'status' => 'command.success',
            'output' => 'Migration complete',
            'exit_code' => 0,
            'started_at' => now()->toISOString(),
            'finished_at' => now()->toISOString(),
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ],
        'relationships' => [],
    ], $overrides);
}

it('lists commands for an environment', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    MockClient::global(array_merge(commandListEnvironmentMocks(), [
        ListCommandsRequest::class => MockResponse::make([
            'data' => [commandApiResponse()],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]));

    $this->artisan('command:list', ['environment' => 'env-1'])
        ->assertSuccessful();
});

it('lists multiple commands', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    MockClient::global(array_merge(commandListEnvironmentMocks(), [
        ListCommandsRequest::class => MockResponse::make([
            'data' => [
                commandApiResponse(),
                commandApiResponse([
                    'id' => 'cmd-456',
                    'attributes' => [
                        'command' => 'php artisan cache:clear',
                        'status' => 'command.failure',
                        'output' => 'Error occurred',
                        'exit_code' => 1,
                        'started_at' => now()->toISOString(),
                        'finished_at' => now()->toISOString(),
                        'created_at' => now()->toISOString(),
                        'updated_at' => now()->toISOString(),
                    ],
                ]),
            ],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]));

    $this->artisan('command:list', ['environment' => 'env-1'])
        ->assertSuccessful();
});

it('handles empty command list', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    MockClient::global(array_merge(commandListEnvironmentMocks(), [
        ListCommandsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]));

    // CommandList does not have an empty check - it will pass with empty table
    $this->artisan('command:list', ['environment' => 'env-1'])
        ->assertSuccessful();
});
