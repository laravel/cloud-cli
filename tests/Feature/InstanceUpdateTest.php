<?php

use App\Client\Requests\UpdateInstanceRequestData;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Instances\GetInstanceRequest;
use App\Client\Resources\Instances\UpdateInstanceRequest;
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

function updatableInstanceResponse(array $attributes = []): array
{
    return ['data' => [
        'id' => 'inst-1',
        'type' => 'instances',
        'attributes' => array_merge([
            'name' => 'web',
            'type' => 'app',
            'size' => 'flex.c-1vcpu-256mb',
            'scaling_type' => 'none',
            'min_replicas' => 1,
            'max_replicas' => 1,
            'uses_scheduler' => true,
            'shutdown_timeout' => null,
            'sleep_with_app' => null,
            'hibernation_timeout' => null,
            'probes' => null,
            'retire_at' => null,
            'retired_at' => null,
        ], $attributes),
        'relationships' => ['environment' => ['data' => ['id' => 'env-1', 'type' => 'environments']]],
    ], 'included' => [createEnvironmentResponse()]];
}

function setupInstanceUpdateMocks(array $attributes = []): stdClass
{
    $sent = new stdClass;
    $sent->body = null;

    $instance = updatableInstanceResponse($attributes);

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()], 200),
        GetInstanceRequest::class => MockResponse::make($instance, 200),
        UpdateInstanceRequest::class => function (PendingRequest $request) use ($sent, $instance) {
            $sent->body = $request->body()->all();

            return MockResponse::make($instance, 200);
        },
    ]);

    return $sent;
}

it('turns scale to zero off by sending a null hibernation timeout', function (string $option) {
    $sent = setupInstanceUpdateMocks(['hibernation_timeout' => 5]);

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        "--{$option}" => 'false',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body)->toHaveKey('hibernation_timeout')
        ->and($sent->body['hibernation_timeout'])->toBeNull()
        ->and($sent->body)->not->toHaveKeys(['uses_sleep_mode', 'sleep_timeout']);
})->with(['scale-to-zero', 'hibernation']);

it('sends the scale to zero timeout as the hibernation timeout', function (array $options) {
    $sent = setupInstanceUpdateMocks();

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        ...$options,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body['hibernation_timeout'])->toBe(12)
        ->and($sent->body)->not->toHaveKeys(['uses_sleep_mode', 'sleep_timeout']);
})->with([
    'timeout only' => [['--scale-to-zero-timeout' => '12']],
    'enabled with timeout' => [['--scale-to-zero' => 'true', '--scale-to-zero-timeout' => '12']],
    'deprecated timeout alias' => [['--hibernation-timeout' => '12']],
]);

it('leaves the hibernation timeout out when scale to zero is not touched', function () {
    $sent = setupInstanceUpdateMocks(['hibernation_timeout' => 5]);

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        '--uses-octane' => 'true',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body)->not->toHaveKey('hibernation_timeout');
});

it('keeps the current timeout when enabling scale to zero that is already on', function () {
    $sent = setupInstanceUpdateMocks(['hibernation_timeout' => 5]);

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        '--scale-to-zero' => 'true',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body)->not->toHaveKey('hibernation_timeout');
});

it('requires a timeout to turn scale to zero on non-interactively', function () {
    $sent = setupInstanceUpdateMocks();

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        '--scale-to-zero' => 'true',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent->body)->toBeNull();
});

it('sends probes in the shape the API expects', function () {
    $sent = setupInstanceUpdateMocks();

    $probes = [
        'startup' => ['path' => '/up', 'tries' => 60],
        'readiness' => ['path' => '/up'],
        'liveness' => ['port' => 8080, 'delay' => null],
    ];

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        '--probes' => json_encode($probes),
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body['probes'])->toBe($probes);
});

it('removes probes when passed null', function () {
    $sent = setupInstanceUpdateMocks(['probes' => ['startup' => ['path' => '/up']]]);

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        '--probes' => 'null',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body)->toHaveKey('probes')
        ->and($sent->body['probes'])->toBeNull();
});

it('rejects probes that do not match the API schema', function (string $probes) {
    $sent = setupInstanceUpdateMocks();

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        '--probes' => $probes,
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent->body)->toBeNull();
})->with([
    'not json' => ['{startup'],
    'list' => ['[1, 2]'],
    'empty object' => ['{}'],
    'unknown check' => ['{"warmup": {"path": "/up"}}'],
    'unknown field' => ['{"startup": {"method": "GET"}}'],
    'string port' => ['{"startup": {"port": "8080"}}'],
    'integer path' => ['{"startup": {"path": 1}}'],
]);

it('maps the instance fields the API returns', function () {
    setupInstanceUpdateMocks([
        'shutdown_timeout' => 90,
        'sleep_with_app' => true,
        'hibernation_timeout' => 5,
        'probes' => ['startup' => ['path' => '/up']],
        'retire_at' => '2026-12-01T00:00:00.000000Z',
        'retired_at' => null,
    ]);

    Artisan::call('instance:get', ['instance' => 'inst-1', '--json' => true]);

    expect(json_decode(Artisan::output(), true))->toMatchArray([
        'shutdownTimeout' => 90,
        'sleepWithApp' => true,
        'hibernationTimeout' => 5,
        'probes' => ['startup' => ['path' => '/up']],
        'retireAt' => '2026-12-01T00:00:00.000000Z',
        'retiredAt' => null,
    ]);
});

it('distinguishes leaving hibernation alone from turning it off', function () {
    $unchanged = new UpdateInstanceRequestData(instanceId: 'inst-1', name: 'web');
    $disabled = new UpdateInstanceRequestData(instanceId: 'inst-1', disableHibernation: true, removeProbes: true);

    expect($unchanged->toRequestData())->toBe(['name' => 'web'])
        ->and($disabled->toRequestData())->toBe(['hibernation_timeout' => null, 'probes' => null]);
});
