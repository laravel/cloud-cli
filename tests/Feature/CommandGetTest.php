<?php

use App\Client\Resources\Commands\GetCommandRequest;
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

function commandGetResponse(array $overrides = []): array
{
    return [
        'data' => array_merge([
            'id' => 'comm-123',
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
        ], $overrides),
        'included' => [],
    ];
}

it('gets command details by ID', function () {
    Prompt::fake();

    MockClient::global([
        GetCommandRequest::class => MockResponse::make(commandGetResponse(), 200),
    ]);

    $this->artisan('command:get', [
        'commandId' => 'comm-123',
    ])->assertSuccessful();
});

it('gets command details with JSON output', function () {
    MockClient::global([
        GetCommandRequest::class => MockResponse::make(commandGetResponse(), 200),
    ]);

    $this->artisan('command:get', [
        'commandId' => 'comm-123',
        '--json' => true,
    ])->assertSuccessful();
});

it('gets command details with null output and exit code', function () {
    Prompt::fake();

    MockClient::global([
        GetCommandRequest::class => MockResponse::make(commandGetResponse([
            'attributes' => [
                'command' => 'php artisan queue:work',
                'status' => 'command.running',
                'output' => null,
                'exit_code' => null,
                'started_at' => now()->toISOString(),
                'finished_at' => null,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ]), 200),
    ]);

    $this->artisan('command:get', [
        'commandId' => 'comm-123',
    ])->assertSuccessful();
});
