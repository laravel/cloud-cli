<?php

use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Instances\GetInstanceRequest;
use App\Client\Resources\Instances\ListInstancesRequest;
use App\Client\Resources\Instances\UpdateInstanceRequest;
use App\Commands\InstanceGet;
use App\Commands\InstanceUpdate;
use App\ConfigRepository;
use App\Prompts\Renderer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Saloon\Exceptions\Request\Statuses\UnprocessableEntityException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function () {
    Sleep::fake();
    Prompt::validateUsing(fn () => null);

    $config = Mockery::mock(ConfigRepository::class);
    $config->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $config);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function healthChecksInstanceResponse(array $attributes = []): array
{
    return [
        'data' => [
            'id' => 'inst-1',
            'type' => 'instances',
            'attributes' => array_merge([
                'name' => 'App',
                'type' => 'app',
                'size' => 'standard-1',
                'scaling_type' => 'none',
                'min_replicas' => 1,
                'max_replicas' => 1,
                'uses_scheduler' => false,
            ], $attributes),
            'relationships' => [
                'environment' => ['data' => ['id' => 'env-1', 'type' => 'environments']],
            ],
        ],
        'included' => [createEnvironmentResponse()],
    ];
}

function setupInstanceHealthCheckMocks(array $attributes = [], int $updateStatus = 200): MockClient
{
    $state = new stdClass;
    $state->response = healthChecksInstanceResponse($attributes);

    return MockClient::global([
        GetInstanceRequest::class => fn () => MockResponse::make($state->response),
        GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()]),
        ListInstancesRequest::class => MockResponse::make([
            'data' => [$state->response['data']],
            'included' => $state->response['included'],
            'links' => ['next' => null],
        ]),
        UpdateInstanceRequest::class => function (PendingRequest $request) use ($state, $updateStatus) {
            if ($updateStatus === 422) {
                return MockResponse::make(['message' => 'Invalid health checks.', 'errors' => ['probes.startup.tries' => ['The startup check exceeds its time budget.']]], 422);
            }

            $attributes = $request->body()->all();

            if (array_key_exists('probes', $attributes)) {
                $state->response['data']['attributes']['probes'] = (array) $attributes['probes'] ?: null;
            }

            return MockResponse::make($state->response);
        },
    ]);
}

it('includes all health check settings in instance get and list JSON', function (string $command, string $argument) {
    $probes = [
        'startup' => ['path' => '/up', 'port' => 3000, 'delay' => 0, 'interval' => 1, 'timeout' => 5, 'tries' => 60],
        'readiness' => ['path' => '/ready', 'port' => 8080, 'delay' => 0, 'interval' => 30, 'timeout' => 5, 'tries' => 3],
        'liveness' => ['path' => '/live', 'port' => 9000, 'delay' => 0, 'interval' => 10, 'timeout' => 10, 'tries' => 4],
    ];
    setupInstanceHealthCheckMocks(['probes' => $probes]);

    $exitCode = Artisan::call($command, [$argument => $argument === 'instance' ? 'inst-1' : 'env-1', '--json' => true, '--no-interaction' => true]);
    $output = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0);
    expect($argument === 'instance' ? $output['probes'] : $output[0]['probes'])->toBe($probes);
})->with([
    'get' => ['instance:get', 'instance'],
    'list' => ['instance:list', 'environment'],
]);

it('filters nested health check fields in JSON', function () {
    setupInstanceHealthCheckMocks(['probes' => ['startup' => ['path' => '/up', 'delay' => 0]]]);

    $exitCode = Artisan::call('instance:get', ['instance' => 'inst-1', '--fields' => 'id,probes.startup.delay', '--no-interaction' => true]);

    expect($exitCode)->toBe(0);
    expect(json_decode(Artisan::output(), true))->toBe(['id' => 'inst-1', 'probes' => ['startup' => ['delay' => 0]]]);
});

it('accepts responses without custom health checks', function (array $attributes) {
    setupInstanceHealthCheckMocks($attributes);

    $exitCode = Artisan::call('instance:get', ['instance' => 'inst-1', '--no-interaction' => true]);

    expect($exitCode)->toBe(0);
    expect(json_decode(Artisan::output(), true)['probes'])->toBeNull();
})->with([
    'feature disabled' => [[]],
    'no overrides' => [['probes' => null]],
    'service' => [['type' => 'service', 'probes' => null]],
]);

it('displays custom health checks and platform defaults in the terminal', function () {
    setupInstanceHealthCheckMocks(['probes' => ['startup' => ['path' => '/up', 'delay' => 0]]]);
    $command = Mockery::mock(InstanceGet::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $command->__construct();
    $command->shouldReceive('isInteractive')->andReturn(true);
    $command->shouldReceive('configurePrompts')->andReturnNull();
    $command->setLaravel($this->app);
    Prompt::fake();
    Renderer::$suppressOutput = false;

    $exitCode = $command->run(new ArrayInput(['instance' => 'inst-1']), new BufferedOutput);

    expect($exitCode)->toBe(0);
    Prompt::assertOutputContains('Health checks');
    Prompt::assertOutputContains('Startup path');
    Prompt::assertOutputContains('/up');
    Prompt::assertOutputContains('Startup delay (seconds)');
    Prompt::assertOutputContains('0');
    Prompt::assertOutputContains('Platform default');
});

it('builds typed health checks from the three probe options', function () {
    $mock = setupInstanceHealthCheckMocks();
    $probes = [
        'startup' => ['path' => '/up', 'port' => 3000, 'delay' => 0, 'interval' => 1, 'timeout' => 5, 'tries' => 60],
        'readiness' => ['port' => 8080],
        'liveness' => ['timeout' => 10],
    ];

    $exitCode = Artisan::call('instance:update', [
        'instance' => 'inst-1',
        '--startup' => ['path=/up', 'port=3000', 'delay=0', 'interval=1', 'timeout=5', 'tries=60'],
        '--readiness' => ['port=8080'],
        '--liveness' => ['timeout=10'],
        '--no-interaction' => true,
    ]);

    expect($exitCode)->toBe(0);
    $mock->assertSent(fn ($request) => $request instanceof UpdateInstanceRequest && $request->body()->all() === ['probes' => $probes]);
    expect(json_decode(Artisan::output(), true)['probes']['startup'])->toBe($probes['startup']);
});

it('preserves other probes and settings when editing one health check', function () {
    $mock = setupInstanceHealthCheckMocks(['probes' => [
        'startup' => ['path' => '/up', 'delay' => 0, 'tries' => 15],
        'readiness' => ['path' => '/ready'],
    ]]);

    $exitCode = Artisan::call('instance:update', [
        'instance' => 'inst-1', '--startup' => ['tries=30'], '--no-interaction' => true,
    ]);

    expect($exitCode)->toBe(0);
    $mock->assertSent(fn ($request) => $request instanceof UpdateInstanceRequest && $request->body()->all() === ['probes' => [
        'startup' => ['path' => '/up', 'delay' => 0, 'tries' => 30],
        'readiness' => ['path' => '/ready'],
    ]]);
});

it('keeps equals signs in health check paths', function () {
    $mock = setupInstanceHealthCheckMocks();

    $this->artisan('instance:update', [
        'instance' => 'inst-1', '--startup' => ['path=/up?check=ready'], '--no-interaction' => true,
    ])->assertSuccessful();

    $mock->assertSent(fn ($request) => $request instanceof UpdateInstanceRequest && $request->body()->all() === [
        'probes' => ['startup' => ['path' => '/up?check=ready']],
    ]);
});

it('preserves custom health checks when updating other settings', function () {
    $probes = ['startup' => ['path' => '/up']];
    $mock = setupInstanceHealthCheckMocks(['probes' => $probes]);

    $exitCode = Artisan::call('instance:update', ['instance' => 'inst-1', '--size' => 'standard-2', '--no-interaction' => true]);

    expect($exitCode)->toBe(0);
    $mock->assertSent(fn ($request) => $request instanceof UpdateInstanceRequest && $request->body()->all() === ['size' => 'standard-2']);
    expect(json_decode(Artisan::output(), true)['probes']['startup']['path'])->toBe('/up');
});

it('resets one probe without clearing another', function () {
    $mock = setupInstanceHealthCheckMocks(['probes' => [
        'startup' => ['path' => '/up'], 'readiness' => ['port' => 8080],
    ]]);

    $this->artisan('instance:update', [
        'instance' => 'inst-1', '--startup' => ['default'], '--no-interaction' => true,
    ])->assertSuccessful();

    $mock->assertSent(fn ($request) => $request instanceof UpdateInstanceRequest && $request->body()->all() === [
        'probes' => ['readiness' => ['port' => 8080]],
    ]);
});

it('clears all overrides when resetting the last configured probe', function () {
    $mock = setupInstanceHealthCheckMocks(['probes' => ['startup' => ['path' => '/up']]]);

    $exitCode = Artisan::call('instance:update', [
        'instance' => 'inst-1', '--startup' => ['default'], '--no-interaction' => true,
    ]);

    expect($exitCode)->toBe(0);
    $mock->assertSent(fn ($request) => $request instanceof UpdateInstanceRequest && json_encode($request->body()->all()) === '{"probes":{}}');
    expect(json_decode(Artisan::output(), true)['probes'])->toBeNull();
});

it('resets an individual setting to its platform default', function () {
    $mock = setupInstanceHealthCheckMocks(['probes' => ['startup' => ['path' => '/up', 'port' => 3000]]]);

    $this->artisan('instance:update', [
        'instance' => 'inst-1', '--startup' => ['path='], '--no-interaction' => true,
    ])->assertSuccessful();

    $mock->assertSent(fn ($request) => $request instanceof UpdateInstanceRequest && $request->body()->all() === [
        'probes' => ['startup' => ['port' => 3000]],
    ]);
});

it('rejects invalid health check settings without sending an update', function (array $input) {
    $mock = setupInstanceHealthCheckMocks();

    $this->artisan('instance:update', [
        'instance' => 'inst-1', '--startup' => $input, '--no-interaction' => true,
    ])->assertFailed();

    $mock->assertNotSent(UpdateInstanceRequest::class);
})->with([
    'missing setting name' => [['/up']],
    'unknown setting' => [['unknown=5']],
    'invalid port' => [['port=invalid']],
    'fractional interval' => [['interval=1.5']],
    'empty option' => [['']],
    'conflicting reset' => [['default', 'path=/up']],
]);

it('propagates API validation failures for health checks', function () {
    $mock = setupInstanceHealthCheckMocks(updateStatus: 422);

    expect(fn () => Artisan::call('instance:update', ['instance' => 'inst-1', '--startup' => ['tries=1000'], '--no-interaction' => true]))
        ->toThrow(UnprocessableEntityException::class, 'The startup check exceeds its time budget.');

    $mock->assertSent(UpdateInstanceRequest::class);
});

it('offers health checks through the existing interactive update form', function () {
    $mock = setupInstanceHealthCheckMocks(['probes' => [
        'startup' => ['path' => '/up'],
        'liveness' => ['timeout' => 10],
    ]]);
    $command = Mockery::mock(InstanceUpdate::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $command->__construct();
    $command->shouldReceive('isInteractive')->andReturn(true);
    $command->shouldReceive('configurePrompts')->andReturnNull();
    $command->setLaravel($this->app);
    Prompt::fake([
        Key::END[0], Key::SPACE, Key::ENTER,
        Key::DOWN, Key::SPACE, Key::ENTER,
        '/ready', Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER,
    ]);
    Renderer::$suppressOutput = false;

    $exitCode = $command->run(new ArrayInput(['instance' => 'inst-1']), new BufferedOutput);

    expect($exitCode)->toBe(0);
    $mock->assertSent(fn ($request) => $request instanceof UpdateInstanceRequest && $request->body()->all() === ['probes' => [
        'startup' => ['path' => '/up'],
        'liveness' => ['timeout' => 10],
        'readiness' => ['path' => '/ready'],
    ]]);
});

it('skips all health checks without sending an empty update', function () {
    $mock = setupInstanceHealthCheckMocks(['probes' => ['startup' => ['path' => '/up']]]);
    $command = Mockery::mock(InstanceUpdate::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $command->__construct();
    $command->shouldReceive('isInteractive')->andReturn(true);
    $command->shouldReceive('configurePrompts')->andReturnNull();
    $command->setLaravel($this->app);
    Prompt::fake([Key::END[0], Key::SPACE, Key::ENTER, Key::ENTER]);
    Renderer::$suppressOutput = false;

    $exitCode = $command->run(new ArrayInput(['instance' => 'inst-1']), new BufferedOutput);

    expect($exitCode)->toBe(0);
    $mock->assertNotSent(UpdateInstanceRequest::class);
    Prompt::assertOutputContains('No changes selected.');
});

it('updates other settings while skipping health checks', function () {
    $mock = setupInstanceHealthCheckMocks(['probes' => ['startup' => ['path' => '/up']]]);
    $command = Mockery::mock(InstanceUpdate::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $command->__construct();
    $command->shouldReceive('isInteractive')->andReturn(true);
    $command->shouldReceive('configurePrompts')->andReturnNull();
    $command->setLaravel($this->app);
    Prompt::fake([
        Key::END[0], Key::SPACE, Key::HOME[0], Key::DOWN, Key::SPACE, Key::ENTER,
        Key::ENTER,
        Key::BACKSPACE, '2', Key::ENTER,
    ]);
    Renderer::$suppressOutput = false;

    $exitCode = $command->run(new ArrayInput(['instance' => 'inst-1']), new BufferedOutput);

    expect($exitCode)->toBe(0);
    $mock->assertSent(fn ($request) => $request instanceof UpdateInstanceRequest && $request->body()->all() === ['min_replicas' => 2]);
});

it('allows correcting health checks after an API validation error', function (string $field, array $retryKeys) {
    $response = healthChecksInstanceResponse();
    $submitted = [];
    $mock = MockClient::global([
        GetInstanceRequest::class => fn () => MockResponse::make($response),
        UpdateInstanceRequest::class => function (PendingRequest $request) use (&$response, &$submitted, $field) {
            $probes = $request->body()->all()['probes'];
            $submitted[] = $probes;

            if (count($submitted) === 1) {
                return MockResponse::make([
                    'message' => 'Invalid health checks.',
                    'errors' => [$field => ['The startup check exceeds its time budget.']],
                ], 422);
            }

            $response['data']['attributes']['probes'] = $probes;

            return MockResponse::make($response);
        },
    ]);
    $command = Mockery::mock(InstanceUpdate::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $command->__construct();
    $command->shouldReceive('isInteractive')->andReturn(true);
    $command->shouldReceive('configurePrompts')->andReturnNull();
    $command->setLaravel($this->app);
    Prompt::fake([
        Key::END[0], Key::SPACE, Key::ENTER,
        Key::SPACE, Key::ENTER,
        Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, '1000', Key::ENTER,
        ...$retryKeys,
        str_repeat(Key::BACKSPACE, 4), '15', Key::ENTER,
    ]);
    Renderer::$suppressOutput = false;

    $exitCode = $command->run(new ArrayInput(['instance' => 'inst-1']), new BufferedOutput);

    expect($exitCode)->toBe(0);
    expect($submitted)->toBe([
        ['startup' => ['tries' => 1000]],
        ['startup' => ['tries' => 15]],
    ]);
    Prompt::assertOutputContains('The startup check exceeds its time budget.');
    Prompt::assertOutputContains('Instance updated');
    $mock->assertSentCount(2, UpdateInstanceRequest::class);
})->with([
    'individual setting' => ['probes.startup.tries', []],
    'probe time budget' => ['probes.startup', array_fill(0, 5, Key::ENTER)],
]);
