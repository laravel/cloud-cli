<?php

use App\Client\Requests\UpdateInstanceRequestData;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Instances\GetInstanceRequest;
use App\Client\Resources\Instances\UpdateInstanceRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Commands\InstanceUpdate;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\Fixtures\InteractiveInstanceGetCommand;
use Tests\Fixtures\InteractiveInstanceUpdateCommand;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

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

it('sends the probe options as checks in the shape the API expects', function () {
    $sent = setupInstanceUpdateMocks();

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        '--startup-probe' => 'path=/up,tries=60',
        '--readiness-probe' => ' path = /up ',
        '--liveness-probe' => 'port=8080,delay=5,interval=10,timeout=3,tries=3',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body['probes'])->toBe([
        'startup' => ['path' => '/up', 'tries' => 60],
        'readiness' => ['path' => '/up'],
        'liveness' => ['port' => 8080, 'delay' => 5, 'interval' => 10, 'timeout' => 3, 'tries' => 3],
    ]);
});

it('keeps the current checks the user did not pass', function () {
    $sent = setupInstanceUpdateMocks(['probes' => [
        'startup' => ['path' => '/up', 'tries' => 60],
        'readiness' => ['path' => '/old', 'timeout' => 5],
        'liveness' => ['port' => 8080],
    ]]);

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        '--readiness-probe' => 'path=/ready',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body['probes'])->toBe([
        'startup' => ['path' => '/up', 'tries' => 60],
        'readiness' => ['path' => '/ready'],
        'liveness' => ['port' => 8080],
    ]);
});

it('removes one check when passed none', function () {
    $sent = setupInstanceUpdateMocks(['probes' => [
        'startup' => ['path' => '/up'],
        'liveness' => ['port' => 8080],
    ]]);

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        '--liveness-probe' => 'none',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body['probes'])->toBe(['startup' => ['path' => '/up']]);
});

it('sends null when removing the last check', function () {
    $sent = setupInstanceUpdateMocks(['probes' => ['startup' => ['path' => '/up'], 'readiness' => null]]);

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        '--startup-probe' => 'none',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body)->toHaveKey('probes')
        ->and($sent->body['probes'])->toBeNull();
});

it('removes every check with --remove-probes', function () {
    $sent = setupInstanceUpdateMocks(['probes' => ['startup' => ['path' => '/up'], 'liveness' => ['port' => 8080]]]);

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        '--remove-probes' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body)->toHaveKey('probes')
        ->and($sent->body['probes'])->toBeNull();
});

it('rejects --remove-probes alongside a probe option', function () {
    $sent = setupInstanceUpdateMocks();

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        '--remove-probes' => true,
        '--readiness-probe' => 'path=/up',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent->body)->toBeNull();
});

it('rejects probe values that do not match the API schema', function (string $option, string $value, string $message) {
    $sent = setupInstanceUpdateMocks();

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        $option => $value,
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent->body)->toBeNull();

    // The headless error goes to STDERR, so check the reason it failed on the parser directly.
    $check = str($option)->between('--', '-probe')->toString();
    $parse = new ReflectionMethod(InstanceUpdate::class, 'parseProbe');

    expect(fn () => $parse->invoke(new InstanceUpdate, $check, $value))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'empty' => ['--startup-probe', '', 'provide comma-separated key=value pairs'],
    'not a pair' => ['--startup-probe', 'path', '"path" is not a key=value pair.'],
    'unknown key' => ['--readiness-probe', 'method=GET', 'unknown key "method"'],
    'repeated key' => ['--readiness-probe', 'path=/a,path=/b', 'path is set more than once.'],
    'missing value' => ['--readiness-probe', 'port=', 'port needs a value.'],
    'path too long' => ['--liveness-probe', 'path=/'.str_repeat('a', 255), 'path may be at most 255 characters.'],
    'non-integer' => ['--liveness-probe', 'port=http', 'port must be a whole number.'],
    'negative' => ['--liveness-probe', 'delay=-1', 'delay must be a whole number.'],
    'port too low' => ['--liveness-probe', 'port=0', 'port must be between 1 and 65535.'],
    'port too high' => ['--liveness-probe', 'port=65536', 'port must be between 1 and 65535.'],
    'delay too high' => ['--startup-probe', 'delay=601', 'delay must be between 0 and 600.'],
    'interval too low' => ['--startup-probe', 'interval=0', 'interval must be between 1 and 300.'],
    'interval too high' => ['--readiness-probe', 'interval=301', 'interval must be between 1 and 300.'],
    'timeout too low' => ['--readiness-probe', 'timeout=0', 'timeout must be between 1 and 120.'],
    'timeout too high' => ['--readiness-probe', 'timeout=121', 'timeout must be between 1 and 120.'],
    'tries too low' => ['--liveness-probe', 'tries=0', 'tries must be between 1 and 1000.'],
    'tries too high' => ['--liveness-probe', 'tries=1001', 'tries must be between 1 and 1000.'],
    'startup budget' => ['--startup-probe', 'delay=30,interval=10,tries=60', 'the startup check may take at most 600 seconds (delay + interval × tries), this one takes 630.'],
]);

it('only enforces the startup budget on the timings given', function (string $value) {
    $sent = setupInstanceUpdateMocks();

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        '--startup-probe' => $value,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body['probes'])->toHaveKey('startup');
})->with([
    'exactly 600 seconds' => ['delay=0,interval=10,tries=60'],
    'no tries' => ['delay=600,interval=300'],
    'no interval' => ['delay=600,tries=1000'],
]);

it('applies the startup budget to the startup check only', function () {
    $sent = setupInstanceUpdateMocks();

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        '--liveness-probe' => 'interval=300,tries=1000',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body['probes'])->toBe(['liveness' => ['interval' => 300, 'tries' => 1000]]);
});

it('leaves probes out when no probe option is passed', function () {
    $sent = setupInstanceUpdateMocks(['probes' => ['startup' => ['path' => '/up']]]);

    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        '--uses-octane' => 'true',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body)->not->toHaveKey('probes');
});

it('edits the picked checks interactively and keeps the rest', function () {
    $sent = setupInstanceUpdateMocks(['probes' => [
        'startup' => ['path' => '/up', 'tries' => 60],
        'readiness' => ['path' => '/old'],
    ]]);

    Artisan::registerCommand(new InteractiveInstanceUpdateCommand);

    Prompt::fake([
        // Health checks is the last entry in the update menu.
        Key::UP, Key::SPACE, Key::ENTER,
        // Pick readiness and liveness.
        Key::DOWN, Key::SPACE, Key::DOWN, Key::SPACE, Key::ENTER,
        // Remove readiness.
        Key::DOWN, Key::ENTER,
        // Liveness: blank path, port 8080, the rest blank.
        Key::ENTER, '8', '0', '8', '0', Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER,
    ]);

    $this->artisan('test:interactive-instance-update', ['instance' => 'inst-1'])->assertSuccessful();

    expect($sent->body['probes'])->toBe([
        'startup' => ['path' => '/up', 'tries' => 60],
        'liveness' => ['port' => 8080],
    ]);
});

it('asks again for the check a nested API error belongs to', function () {
    $instance = updatableInstanceResponse();
    $bodies = [];

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()], 200),
        GetInstanceRequest::class => MockResponse::make($instance, 200),
        UpdateInstanceRequest::class => function (PendingRequest $request) use (&$bodies, $instance) {
            $bodies[] = $request->body()->all();

            return count($bodies) === 1
                ? MockResponse::make(['message' => 'Invalid.', 'errors' => ['probes.liveness.port' => ['The port is already in use.']]], 422)
                : MockResponse::make($instance, 200);
        },
    ]);

    Artisan::registerCommand(new InteractiveInstanceUpdateCommand);

    Prompt::fake([
        Key::UP, Key::SPACE, Key::ENTER,
        Key::DOWN, Key::DOWN, Key::SPACE, Key::ENTER,
        Key::ENTER, '8', '0', '8', '0', Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER,
        // Asked for the liveness check again, starting from what was entered.
        Key::ENTER, Key::BACKSPACE, Key::BACKSPACE, Key::BACKSPACE, Key::BACKSPACE, '9', '0', '0', '0', Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER,
    ]);

    $this->artisan('test:interactive-instance-update', ['instance' => 'inst-1'])->assertSuccessful();

    expect($bodies)->toHaveCount(2)
        ->and($bodies[0]['probes'])->toBe(['liveness' => ['port' => 8080]])
        ->and($bodies[1]['probes'])->toBe(['liveness' => ['port' => 9000]]);
});

it('asks again when the interactive startup check runs over the time limit', function () {
    $sent = setupInstanceUpdateMocks();

    Artisan::registerCommand(new InteractiveInstanceUpdateCommand);

    Prompt::fake([
        Key::UP, Key::SPACE, Key::ENTER,
        Key::SPACE, Key::ENTER,
        // interval 10, tries 61 is 610 seconds.
        Key::ENTER, Key::ENTER, Key::ENTER, '1', '0', Key::ENTER, Key::ENTER, '6', '1', Key::ENTER,
        // Keep everything, but drop tries to 60.
        Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::BACKSPACE, '0', Key::ENTER,
    ]);

    $this->artisan('test:interactive-instance-update', ['instance' => 'inst-1'])->assertSuccessful();

    expect($sent->body['probes'])->toBe(['startup' => ['interval' => 10, 'tries' => 60]]);
});

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

it('maps Octane and Inertia SSR, keeping null for instances they do not apply to', function (array $attributes, ?bool $octane, ?bool $ssr) {
    setupInstanceUpdateMocks($attributes);

    Artisan::call('instance:get', ['instance' => 'inst-1', '--json' => true]);

    $json = json_decode(Artisan::output(), true);

    expect($json)->toHaveKeys(['usesOctane', 'usesInertiaSsr'])
        ->and($json['usesOctane'])->toBe($octane)
        ->and($json['usesInertiaSsr'])->toBe($ssr);
})->with([
    'app' => [['uses_octane' => true, 'uses_inertia_ssr' => false], true, false],
    'worker' => [['type' => 'service', 'uses_octane' => null, 'uses_inertia_ssr' => null], null, null],
]);

it('selects Octane and Inertia SSR through --fields', function () {
    setupInstanceUpdateMocks(['uses_octane' => false, 'uses_inertia_ssr' => true]);

    Artisan::call('instance:get', ['instance' => 'inst-1', '--json' => true, '--fields' => 'usesOctane,usesInertiaSsr']);

    expect(json_decode(Artisan::output(), true))->toBe(['usesOctane' => false, 'usesInertiaSsr' => true]);
});

it('shows Octane and Inertia SSR for app instances only', function () {
    Artisan::registerCommand(new InteractiveInstanceGetCommand);

    setupInstanceUpdateMocks(['uses_octane' => true, 'uses_inertia_ssr' => false]);
    Artisan::call('test:interactive-instance-get', ['instance' => 'inst-1']);

    // Each label sits on its own line above its value.
    expect(preg_replace('/\e\[[0-9;]*m/', '', Artisan::output()))
        ->toMatch('/Octane\W+Yes/u')
        ->toMatch('/Inertia SSR\W+No/u');

    MockClient::destroyGlobal();
    setupInstanceUpdateMocks(['type' => 'service', 'uses_octane' => null, 'uses_inertia_ssr' => null]);
    Artisan::call('test:interactive-instance-get', ['instance' => 'inst-1']);

    expect(Artisan::output())->not->toContain('Octane')->not->toContain('Inertia SSR');
});

it('starts the interactive Octane and Inertia SSR prompts from the current values', function () {
    $sent = setupInstanceUpdateMocks(['uses_octane' => true, 'uses_inertia_ssr' => true]);

    Artisan::registerCommand(new InteractiveInstanceUpdateCommand);

    Prompt::fake([
        // Octane and Inertia SSR are the 8th and 9th entries in the update menu.
        Key::DOWN, Key::DOWN, Key::DOWN, Key::DOWN, Key::DOWN, Key::DOWN, Key::DOWN, Key::SPACE,
        Key::DOWN, Key::SPACE, Key::ENTER,
        // Accept both defaults.
        Key::ENTER, Key::ENTER,
    ]);

    $this->artisan('test:interactive-instance-update', ['instance' => 'inst-1'])->assertSuccessful();

    expect($sent->body)->toMatchArray(['uses_octane' => true, 'uses_inertia_ssr' => true]);
});

it('distinguishes leaving hibernation alone from turning it off', function () {
    $unchanged = new UpdateInstanceRequestData(instanceId: 'inst-1', name: 'web');
    $disabled = new UpdateInstanceRequestData(instanceId: 'inst-1', disableHibernation: true, removeProbes: true);

    expect($unchanged->toRequestData())->toBe(['name' => 'web'])
        ->and($disabled->toRequestData())->toBe(['hibernation_timeout' => null, 'probes' => null]);
});
