<?php

/**
 * EnvironmentLogs tests.
 *
 * Note: The environment:logs command uses EnvironmentLogsPrompt for interactive display
 * and optional live-tailing. These tests cover the command's bootstrapping, log fetching,
 * and the empty-logs failure path. The EnvironmentLogsPrompt rendering itself is not
 * tested as it requires a real terminal.
 */

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentLogsRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
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

function setupEnvironmentLogsMocks(array $logsData = []): void
{
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
        ListEnvironmentLogsRequest::class => MockResponse::make([
            'data' => $logsData,
        ], 200),
    ]);
}

it('returns failure when no logs are found', function () {
    Prompt::fake();

    setupEnvironmentLogsMocks([]);

    $this->artisan('environment:logs', [
        'application' => 'My App',
        'environment' => 'production',
    ])->assertFailed();
});

it('returns failure when no logs are found with --hours filter', function () {
    Prompt::fake();

    setupEnvironmentLogsMocks([]);

    $this->artisan('environment:logs', [
        'application' => 'My App',
        'environment' => 'production',
        '--hours' => 2,
    ])->assertFailed();
});

it('returns failure when no logs are found with --minutes filter', function () {
    Prompt::fake();

    setupEnvironmentLogsMocks([]);

    $this->artisan('environment:logs', [
        'application' => 'My App',
        'environment' => 'production',
        '--minutes' => 30,
    ])->assertFailed();
});

it('returns failure when no logs are found with --days filter', function () {
    Prompt::fake();

    setupEnvironmentLogsMocks([]);

    $this->artisan('environment:logs', [
        'application' => 'My App',
        'environment' => 'production',
        '--days' => 7,
    ])->assertFailed();
});

it('returns failure when no logs are found with --from filter', function () {
    Prompt::fake();

    setupEnvironmentLogsMocks([]);

    $this->artisan('environment:logs', [
        'application' => 'My App',
        'environment' => 'production',
        '--from' => '2025-01-01 00:00:00',
    ])->assertFailed();
});

it('returns failure when no logs are found with --from and --to filters', function () {
    Prompt::fake();

    setupEnvironmentLogsMocks([]);

    $this->artisan('environment:logs', [
        'application' => 'My App',
        'environment' => 'production',
        '--from' => '2025-01-01 00:00:00',
        '--to' => '2025-01-02 00:00:00',
    ])->assertFailed();
});

it('outputs logs as JSON when --json flag is used', function () {
    Prompt::fake();

    $logsData = [
        [
            'message' => 'Test log entry 1',
            'level' => 'info',
            'type' => 'application',
            'logged_at' => '2025-01-01T00:00:00Z',
            'data' => null,
        ],
        [
            'message' => 'Test log entry 2',
            'level' => 'warning',
            'type' => 'application',
            'logged_at' => '2025-01-01T00:01:00Z',
            'data' => null,
        ],
    ];

    setupEnvironmentLogsMocks($logsData);

    $this->artisan('environment:logs', [
        'application' => 'My App',
        'environment' => 'production',
        '--json' => true,
    ])->assertSuccessful();
});
