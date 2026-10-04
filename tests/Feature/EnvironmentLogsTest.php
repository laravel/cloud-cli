<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentLogsRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function environmentLogResponse(string $message, string $loggedAt): array
{
    return [
        'message' => $message,
        'level' => 'info',
        'type' => 'application',
        'logged_at' => $loggedAt,
        'data' => null,
    ];
}

/**
 * Mirrors the API: the first page holds the newest lines and each cursor walks
 * further back in time, while every page is itself ordered oldest-first.
 *
 * @param  array<string, array{logs: array<int, array<string, mixed>>, cursor: string}>  $pagesByCursor
 */
function mockEnvironmentLogs(array $pagesByCursor): MockClient
{
    return MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetApplicationRequest::class => MockResponse::make(['data' => createApplicationResponse()], 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()], 200),
        ListEnvironmentLogsRequest::class => function (PendingRequest $request) use ($pagesByCursor) {
            $page = $pagesByCursor[$request->query()->get('cursor') ?? ''] ?? ['logs' => [], 'cursor' => ''];

            return MockResponse::make([
                'data' => $page['logs'],
                'meta' => ['cursor' => $page['cursor'], 'type' => 'all'],
            ], 200);
        },
    ]);
}

function threeLogPages(): array
{
    return [
        '' => ['logs' => [
            environmentLogResponse('line 5', '2026-10-01T10:00:05Z'),
            environmentLogResponse('line 6', '2026-10-01T10:00:06Z'),
        ], 'cursor' => 'cursor-1'],
        'cursor-1' => ['logs' => [
            environmentLogResponse('line 3', '2026-10-01T10:00:03Z'),
            environmentLogResponse('line 4', '2026-10-01T10:00:04Z'),
        ], 'cursor' => 'cursor-2'],
        'cursor-2' => ['logs' => [
            environmentLogResponse('line 1', '2026-10-01T10:00:01Z'),
            environmentLogResponse('line 2', '2026-10-01T10:00:02Z'),
        ], 'cursor' => 'cursor-3'],
    ];
}

function callEnvironmentLogs(array $options = []): int
{
    return Artisan::call('environment:logs', [
        'application' => 'app-123',
        'environment' => 'env-1',
        '--json' => true,
        '--no-interaction' => true,
        ...$options,
    ]);
}

it('fetches a single page when no limit is given', function () {
    $mockClient = mockEnvironmentLogs(threeLogPages());

    expect(callEnvironmentLogs())->toBe(0);

    $mockClient->assertSentCount(1, ListEnvironmentLogsRequest::class);
    expect(collect(json_decode(Artisan::output(), true))->pluck('message')->all())
        ->toBe(['line 5', 'line 6']);
});

it('follows the cursor until the limit is reached and keeps the output chronological', function () {
    $mockClient = mockEnvironmentLogs(threeLogPages());

    expect(callEnvironmentLogs(['--limit' => 3]))->toBe(0);

    $mockClient->assertSentCount(2, ListEnvironmentLogsRequest::class);
    expect(collect(json_decode(Artisan::output(), true))->pluck('message')->all())
        ->toBe(['line 4', 'line 5', 'line 6']);
});

it('sends the previous page cursor on the next request', function () {
    $mockClient = mockEnvironmentLogs(threeLogPages());

    callEnvironmentLogs(['--limit' => 4]);

    expect($mockClient->getLastPendingRequest()->query()->get('cursor'))->toBe('cursor-1');
});

it('stops paging when the API runs out of logs', function () {
    $mockClient = mockEnvironmentLogs(threeLogPages());

    expect(callEnvironmentLogs(['--limit' => 1000]))->toBe(0);

    // The fourth request returns an empty page, which ends the walk.
    $mockClient->assertSentCount(4, ListEnvironmentLogsRequest::class);
    expect(collect(json_decode(Artisan::output(), true))->pluck('message')->all())
        ->toBe(['line 1', 'line 2', 'line 3', 'line 4', 'line 5', 'line 6']);
});

it('stops paging when the API returns no cursor', function () {
    $pages = threeLogPages();
    $pages['']['cursor'] = '';

    $mockClient = mockEnvironmentLogs($pages);

    callEnvironmentLogs(['--limit' => 500]);

    $mockClient->assertSentCount(1, ListEnvironmentLogsRequest::class);
});

it('returns what was fetched when a later page is rate limited', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetApplicationRequest::class => MockResponse::make(['data' => createApplicationResponse()], 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()], 200),
        ListEnvironmentLogsRequest::class => fn (PendingRequest $request) => $request->query()->get('cursor')
            ? MockResponse::make(['message' => 'Too Many Attempts.'], 429)
            : MockResponse::make([
                'data' => [environmentLogResponse('line 6', '2026-10-01T10:00:06Z')],
                'meta' => ['cursor' => 'cursor-1'],
            ], 200),
    ]);

    expect(callEnvironmentLogs(['--limit' => 200]))->toBe(0);
    expect(collect(json_decode(Artisan::output(), true))->pluck('message')->all())->toBe(['line 6']);
});

it('rejects an invalid limit before calling the API', function (string $limit) {
    $mockClient = mockEnvironmentLogs(threeLogPages());

    expect(callEnvironmentLogs(['--limit' => $limit]))->toBe(1);

    $mockClient->assertSentCount(0);
})->with(['0', '-5', 'abc', '1001', '2.5']);
