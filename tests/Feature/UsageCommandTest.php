<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Usage\GetUsageRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Sleep;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Sleep::fake();

    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

it('outputs full usage payload as camelCase JSON', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetUsageRequest::class => MockResponse::make(usageResponse(), 200),
    ]);

    $exitCode = Artisan::call('usage', ['--json' => true, '--no-interaction' => true]);

    expect($exitCode)->toBe(0);

    $decoded = json_decode(Artisan::output(), true);

    expect($decoded)
        ->toBeArray()
        ->toHaveKeys([
            'currency',
            'period',
            'currentSpendCents',
            'bandwidth',
            'credits',
            'alert',
            'resourcesTotalCostCents',
            'addonsTotalCostCents',
            'applicationCount',
            'applicationsTotalCostCents',
            'environmentUsageTotalCostCents',
            'lastUpdatedAt',
            'availablePeriods',
            'databases',
            'caches',
            'buckets',
            'websockets',
            'applications',
            'addonItems',
            'environmentUsageItems',
        ]);

    expect($decoded['currency'])->toBe('USD');
    expect($decoded['currentSpendCents'])->toBe(12345);
    expect($decoded['bandwidth'])->toBe([
        'costCents' => 100,
        'usagePercentage' => 42,
        'allowanceBytes' => 107374182400,
    ]);
    expect($decoded['credits'])->toBe(['usedCents' => 250, 'totalCents' => 1000]);
    expect($decoded['alert'])->toBe(['thresholdCents' => 50000, 'remainingPercentage' => 75]);

    expect($decoded['databases'][0])->toMatchArray([
        'name' => 'primary',
        'identifier' => 'db-1',
        'computeUnitLabel' => 'CU',
        'storageGb' => 12.5,
        'totalCents' => 4000,
    ]);

    expect($decoded['buckets'][0])->toMatchArray([
        'classARequestsCount' => 1000,
        'classBRequestsCount' => 5000,
    ]);

    expect($decoded['environmentUsageItems'][0])->toMatchArray([
        'computeProfile' => 'standard-1',
        'computeDescription' => '1 vCPU / 2GB',
        'cpuHours' => 720,
    ]);
});

it('does not call application:list when only emitting JSON', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetUsageRequest::class => MockResponse::make(usageResponse(), 200),
    ]);

    Artisan::call('usage', ['--json' => true, '--no-interaction' => true]);

    MockClient::global()->assertNotSent(ListApplicationsRequest::class);
});

it('sends the requested period as a query parameter', function (string $input, int $expected) {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetUsageRequest::class => MockResponse::make(usageResponse(['meta' => ['period' => $expected]]), 200),
    ]);

    Artisan::call('usage', ['--period' => $input, '--json' => true, '--no-interaction' => true]);

    MockClient::global()->assertSent(function ($request) use ($expected) {
        return $request instanceof GetUsageRequest
            && $request->query()->get('period') === $expected;
    });
})->with([
    ['current', 0],
    ['previous', 1],
    ['1', 1],
    ['2', 2],
    ['3', 3],
]);

it('rejects invalid --period values', function (string $input) {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetUsageRequest::class => MockResponse::make(usageResponse(), 200),
    ]);

    $exitCode = Artisan::call('usage', ['--period' => $input, '--json' => true, '--no-interaction' => true]);

    expect($exitCode)->toBe(1);
    MockClient::global()->assertNotSent(GetUsageRequest::class);
})->with(['0', '-1', '4', 'last', '2026-03', '']);

it('handles null bandwidth, credits, and alert gracefully', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetUsageRequest::class => MockResponse::make([
            'data' => [
                'summary' => [
                    'current_spend_cents' => 0,
                    'bandwidth' => null,
                    'credits' => null,
                    'alert' => null,
                ],
                'resources' => ['total_cost_cents' => 0, 'databases' => [], 'caches' => [], 'buckets' => [], 'websockets' => []],
                'addons' => ['total_cost_cents' => 0, 'items' => []],
                'application_totals' => ['total_cost_cents' => 0, 'application_count' => 0, 'applications' => []],
                'environment_usage' => ['total_cost_cents' => 0, 'items' => []],
            ],
            'meta' => [
                'currency' => 'USD',
                'period' => 0,
                'available_periods' => [],
                'last_updated_at' => '2026-04-29T10:00:00Z',
            ],
        ], 200),
    ]);

    $exitCode = Artisan::call('usage', ['--json' => true, '--no-interaction' => true]);

    expect($exitCode)->toBe(0);

    $decoded = json_decode(Artisan::output(), true);

    expect($decoded['bandwidth'])->toBeNull();
    expect($decoded['credits'])->toBeNull();
    expect($decoded['alert'])->toBeNull();
});
