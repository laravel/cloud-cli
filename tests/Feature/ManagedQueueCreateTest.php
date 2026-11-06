<?php

use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Instances\CreateInstanceRequest;
use App\Client\Resources\Instances\ListInstanceSizesRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Sleep;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    Sleep::fake();

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    // Without this the command would offer to `composer require` the AWS SDK into this repo.
    $composer = Mockery::mock(Composer::class);
    $composer->shouldReceive('setWorkingPath')->andReturnSelf();
    $composer->shouldReceive('hasPackage')->with('aws/aws-sdk-php')->andReturnTrue();
    $this->app->instance(Composer::class, $composer);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function setupManagedQueueCreateMocks(): Closure
{
    $sentBody = new stdClass;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()], 200),
        ListInstanceSizesRequest::class => MockResponse::make([
            'data' => [
                'managed_queue' => [
                    managedQueueSize('mq-pro-1gb'),
                    managedQueueSize('mq.pro.1gb'),
                ],
            ],
        ], 200),
        CreateInstanceRequest::class => function (PendingRequest $request) use ($sentBody) {
            $sentBody->value = $request->body()->all();

            return MockResponse::make([
                'data' => [
                    'id' => 'instance-1',
                    'type' => 'instances',
                    'attributes' => [
                        'name' => $sentBody->value['name'].(($sentBody->value['fifo'] ?? false) ? '.fifo' : ''),
                        'type' => 'managed_queue',
                        'queue_type' => ($sentBody->value['fifo'] ?? false) ? 'fifo' : 'standard',
                        'size' => $sentBody->value['size'],
                        'scaling_type' => 'custom',
                        'min_replicas' => 1,
                        'max_replicas' => 3,
                        'uses_scheduler' => false,
                    ],
                ],
            ], 201);
        },
    ]);

    return fn () => $sentBody->value ?? null;
}

it('leaves out the polling interval for a current-generation size', function () {
    $sentBody = setupManagedQueueCreateMocks();

    $this->artisan('managed-queue:create', [
        'environment' => 'env-1',
        '--name' => 'emails',
        '--size' => 'mq.pro.1gb',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())
        ->toMatchArray(['size' => 'mq.pro.1gb'])
        ->not->toHaveKey('polling_interval')
        ->not->toHaveKey('fifo');
});

it('ignores --polling-interval for a current-generation size', function () {
    $sentBody = setupManagedQueueCreateMocks();

    $this->artisan('managed-queue:create', [
        'environment' => 'env-1',
        '--name' => 'emails',
        '--size' => 'mq.pro.1gb',
        '--polling-interval' => '10',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->not->toHaveKey('polling_interval');
});

it('sends the polling interval for a previous-generation size', function () {
    $sentBody = setupManagedQueueCreateMocks();

    $this->artisan('managed-queue:create', [
        'environment' => 'env-1',
        '--name' => 'emails',
        '--size' => 'mq-pro-1gb',
        '--polling-interval' => '10',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->toMatchArray([
        'size' => 'mq-pro-1gb',
        'polling_interval' => 10,
    ]);
});

it('defaults the polling interval for a previous-generation size', function () {
    $sentBody = setupManagedQueueCreateMocks();

    $this->artisan('managed-queue:create', [
        'environment' => 'env-1',
        '--name' => 'emails',
        '--size' => 'mq-pro-1gb',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody()['polling_interval'])->toBe(5);
});

it('creates a FIFO queue from the base name with --fifo', function () {
    $sentBody = setupManagedQueueCreateMocks();

    Artisan::call('managed-queue:create', [
        'environment' => 'env-1',
        '--name' => 'orders',
        '--size' => 'mq.pro.1gb',
        '--fifo' => true,
        '--json' => true,
        '--no-interaction' => true,
    ]);

    expect(json_decode(Artisan::output(), true))->toMatchArray([
        'name' => 'orders.fifo',
        'queueType' => 'fifo',
    ]);

    expect($sentBody())->toMatchArray([
        'name' => 'orders',
        'type' => 'managed_queue',
        'fifo' => true,
    ]);
    expect($sentBody()['background_processes'][0]['config']['queue'])->toBe('orders');
});
