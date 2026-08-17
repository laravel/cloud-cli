<?php

use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Instances\CreateInstanceRequest;
use App\Client\Resources\Instances\ListInstanceSizesRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use Illuminate\Support\Sleep;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    Sleep::fake();

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function instanceSizesResponse(): array
{
    return [
        'data' => [
            'service' => [
                [
                    'name' => 'standard-1',
                    'label' => 'Standard 1',
                    'description' => '1 vCPU / 2GB',
                    'cpu_type' => 'shared',
                    'compute_class' => 'standard',
                    'cpu_count' => 1,
                    'memory_mib' => 2048,
                ],
            ],
        ],
    ];
}

function createdInstanceResponse(array $attributes = []): array
{
    return [
        'data' => [
            'id' => 'instance-1',
            'type' => 'instances',
            'attributes' => array_merge([
                'name' => 'analytics',
                'type' => 'service',
                'size' => 'standard-1',
                'scaling_type' => 'none',
                'min_replicas' => 1,
                'max_replicas' => 1,
                'uses_scheduler' => false,
            ], $attributes),
        ],
    ];
}

function setupInstanceCreateMocks(): Closure
{
    $sentBody = new stdClass;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()], 200),
        ListInstanceSizesRequest::class => MockResponse::make(instanceSizesResponse(), 200),
        CreateInstanceRequest::class => function (PendingRequest $request) use ($sentBody) {
            $sentBody->value = $request->body()->all();

            return MockResponse::make(createdInstanceResponse(), 201);
        },
    ]);

    return fn () => $sentBody->value ?? null;
}

it('creates an instance non-interactively without a scaling type', function () {
    $sentBody = setupInstanceCreateMocks();

    $this->artisan('instance:create', [
        'environment' => 'env-1',
        '--name' => 'analytics',
        '--size' => 'standard-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->toMatchArray([
        'name' => 'analytics',
        'size' => 'standard-1',
        'scaling_type' => 'none',
        'min_replicas' => 1,
        'max_replicas' => 1,
        'uses_scheduler' => false,
    ]);
});

it('creates an instance non-interactively with an explicit scaling type', function (string $scalingType) {
    $sentBody = setupInstanceCreateMocks();

    $this->artisan('instance:create', [
        'environment' => 'env-1',
        '--name' => 'analytics',
        '--size' => 'standard-1',
        '--scaling-type' => $scalingType,
        '--uses-scheduler' => 'false',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody()['scaling_type'])->toBe($scalingType);
})->with(['none', 'custom', 'auto']);

it('accepts a scaling type in any case', function () {
    $sentBody = setupInstanceCreateMocks();

    $this->artisan('instance:create', [
        'environment' => 'env-1',
        '--name' => 'analytics',
        '--size' => 'standard-1',
        '--scaling-type' => 'CUSTOM',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody()['scaling_type'])->toBe('custom');
});

it('sends scaling thresholds when the scaling type is custom', function () {
    $sentBody = setupInstanceCreateMocks();

    $this->artisan('instance:create', [
        'environment' => 'env-1',
        '--name' => 'analytics',
        '--size' => 'standard-1',
        '--scaling-type' => 'custom',
        '--min-replicas' => '2',
        '--max-replicas' => '6',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->toMatchArray([
        'scaling_type' => 'custom',
        'min_replicas' => 2,
        'max_replicas' => 6,
        'scaling_cpu_threshold_percentage' => 50,
        'scaling_memory_threshold_percentage' => 50,
    ]);
});

it('does not send scaling thresholds when the scaling type is none', function () {
    $sentBody = setupInstanceCreateMocks();

    $this->artisan('instance:create', [
        'environment' => 'env-1',
        '--name' => 'analytics',
        '--size' => 'standard-1',
        '--scaling-type' => 'none',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->not->toHaveKey('scaling_cpu_threshold_percentage');
    expect($sentBody())->not->toHaveKey('scaling_memory_threshold_percentage');
});

it('fails with a readable message on an invalid scaling type', function () {
    $sentBody = setupInstanceCreateMocks();

    $this->artisan('instance:create', [
        'environment' => 'env-1',
        '--name' => 'analytics',
        '--size' => 'standard-1',
        '--scaling-type' => 'bogus',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sentBody())->toBeNull();
});
