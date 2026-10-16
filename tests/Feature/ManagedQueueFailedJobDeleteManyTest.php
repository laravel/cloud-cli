<?php

use App\Client\Resources\Instances\DeleteManagedQueueFailedJobsRequest;
use App\Client\Resources\Instances\GetInstanceRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
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

function setupFailedJobsBulkDeleteMocks(string $type = 'managed_queue'): stdClass
{
    $sent = new stdClass;
    $sent->query = null;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetInstanceRequest::class => MockResponse::make(['data' => [
            'id' => 'inst-1',
            'type' => 'instances',
            'attributes' => [
                'name' => 'jobs',
                'type' => $type,
                'size' => 'flex.c-1vcpu-256mb',
                'scaling_type' => 'none',
                'min_replicas' => 0,
                'max_replicas' => 1,
                'uses_scheduler' => false,
            ],
        ]], 200),
        DeleteManagedQueueFailedJobsRequest::class => function (PendingRequest $request) use ($sent) {
            $sent->method = $request->getMethod()->value;
            $sent->path = parse_url($request->getUri(), PHP_URL_PATH);
            $sent->query = $request->query()->all();

            return MockResponse::make('', 202);
        },
    ]);

    return $sent;
}

it('deletes the given failed jobs in one request', function () {
    $sent = setupFailedJobsBulkDeleteMocks();

    $this->artisan('managed-queue:delete-failed-jobs', [
        'instance' => 'inst-1',
        '--job' => ['job-a', 'job-b,job-c', 'job-a'],
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->method)->toBe('DELETE')
        ->and($sent->path)->toEndWith('/instances/inst-1/failed-jobs')
        ->and($sent->query)->toBe(['jobIds' => 'job-a,job-b,job-c']);
});

it('deletes every job that failed before a date', function () {
    $sent = setupFailedJobsBulkDeleteMocks();

    $this->artisan('managed-queue:delete-failed-jobs', [
        'instance' => 'inst-1',
        '--failed-before' => '2026-10-01T00:00:00Z',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->query)->toBe(['failedBefore' => '2026-10-01T00:00:00Z']);
});

it('requires --force when not interactive', function () {
    $sent = setupFailedJobsBulkDeleteMocks();

    $this->artisan('managed-queue:delete-failed-jobs', [
        'instance' => 'inst-1',
        '--job' => ['job-a'],
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent->query)->toBeNull();
});

it('requires jobs or a date when not interactive', function () {
    $sent = setupFailedJobsBulkDeleteMocks();

    $this->artisan('managed-queue:delete-failed-jobs', [
        'instance' => 'inst-1',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent->query)->toBeNull();
});

it('refuses instances that are not managed queues', function () {
    $sent = setupFailedJobsBulkDeleteMocks('service');

    $this->artisan('managed-queue:delete-failed-jobs', [
        'instance' => 'inst-1',
        '--job' => ['job-a'],
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent->query)->toBeNull();
});
