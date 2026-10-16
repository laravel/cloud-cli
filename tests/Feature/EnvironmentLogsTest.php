<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentLogsRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(false)->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    $this->query = null;
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function logEntry(string $message, string $loggedAt = '2026-10-15T12:00:00Z'): array
{
    return [
        'message' => $message,
        'level' => 'info',
        'type' => 'application',
        'logged_at' => $loggedAt,
        'data' => null,
    ];
}

function mockEnvironmentLogs(array $logs, string $cursor): void
{
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetApplicationRequest::class => MockResponse::make(['data' => createApplicationResponse()], 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()], 200),
        ListEnvironmentLogsRequest::class => function (PendingRequest $request) use ($logs, $cursor) {
            test()->query = $request->query()->all();

            return MockResponse::make([
                'data' => $logs,
                'meta' => [
                    'cursor' => $cursor,
                    'type' => $request->query()->get('type', 'all'),
                    'from' => $request->query()->get('from'),
                    'to' => $request->query()->get('to'),
                ],
            ], 200);
        },
    ]);
}

function runEnvironmentLogs(array $options = []): array
{
    Artisan::call('environment:logs', [
        'application' => 'app-123',
        'environment' => 'env-1',
        '--json' => true,
        ...$options,
    ]);

    return json_decode(Artisan::output(), true);
}

it('returns the logs with the cursor for the next page', function () {
    mockEnvironmentLogs([logEntry('first'), logEntry('second')], 'next-page-cursor');

    $output = runEnvironmentLogs();

    expect(collect($output['logs'])->pluck('message')->all())->toBe(['first', 'second'])
        ->and($output['cursor'])->toBe('next-page-cursor');
});

it('passes the cursor and filters to the API', function () {
    mockEnvironmentLogs([logEntry('match')], 'next-page-cursor');

    runEnvironmentLogs([
        '--from' => '2026-10-14T00:00:00+00:00',
        '--to' => '2026-10-15T00:00:00+00:00',
        '--cursor' => 'previous-cursor',
        '--type' => 'access',
        '--query' => 'GET /health',
        '--case-sensitive' => true,
        '--whole-word' => true,
        '--instance' => ['inst-1', 'inst-2'],
    ]);

    expect($this->query)->toBe([
        'from' => '2026-10-14T00:00:00+00:00',
        'to' => '2026-10-15T00:00:00+00:00',
        'cursor' => 'previous-cursor',
        'type' => 'access',
        'query' => 'GET /health',
        'case_sensitive' => true,
        'whole_word' => true,
        'instances' => ['inst-1', 'inst-2'],
    ]);
});

it('leaves out filters that were not given', function () {
    mockEnvironmentLogs([logEntry('any')], 'next-page-cursor');

    runEnvironmentLogs();

    expect(array_keys($this->query))->toBe(['from', 'to']);
});

it('returns a null cursor when the API has no more logs', function () {
    mockEnvironmentLogs([], '');

    expect(runEnvironmentLogs())->toBe(['logs' => [], 'cursor' => null]);
});

it('rejects a log type the API does not accept', function () {
    mockEnvironmentLogs([], '');

    $this->artisan('environment:logs', [
        'application' => 'app-123',
        'environment' => 'env-1',
        '--type' => 'exception',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($this->query)->toBeNull();
});
