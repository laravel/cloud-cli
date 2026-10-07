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
