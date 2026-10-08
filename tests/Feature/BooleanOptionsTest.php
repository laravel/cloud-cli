<?php

use App\Client\Resources\Domains\CreateDomainRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Instances\GetInstanceRequest;
use App\Client\Resources\Instances\UpdateInstanceRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    $this->sent = new stdClass;

    $instance = ['data' => [
        'id' => 'inst-1',
        'type' => 'instances',
        'attributes' => [
            'name' => 'web',
            'type' => 'service',
            'size' => 'flex.c-1vcpu-256mb',
            'scaling_type' => 'none',
            'min_replicas' => 1,
            'max_replicas' => 1,
            'uses_scheduler' => true,
            'uses_octane' => true,
            'uses_inertia_ssr' => true,
            'uses_sleep_mode' => true,
        ],
        'relationships' => ['environment' => ['data' => ['id' => 'env-1', 'type' => 'environments']]],
    ], 'included' => [createEnvironmentResponse()]];

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()], 200),
        GetInstanceRequest::class => MockResponse::make($instance, 200),
        UpdateInstanceRequest::class => function (PendingRequest $request) use ($instance) {
            $this->sent->body = $request->body()->all();

            return MockResponse::make($instance, 200);
        },
        CreateDomainRequest::class => function (PendingRequest $request) {
            $this->sent->body = $request->body()->all();

            return MockResponse::make(['data' => [
                'id' => 'dom-1',
                'type' => 'domains',
                'attributes' => ['name' => 'example.com'],
            ]], 201);
        },
    ]);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

it('sends instance options passed as false as false', function (string $option, string $field) {
    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        "--{$option}" => 'false',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sent->body[$field])->toBeFalse();
})->with([
    ['uses-scheduler', 'uses_scheduler'],
    ['uses-octane', 'uses_octane'],
    ['uses-inertia-ssr', 'uses_inertia_ssr'],
    ['scale-to-zero', 'uses_sleep_mode'],
    ['hibernation', 'uses_sleep_mode'],
]);

it('sends instance options passed as true as true', function () {
    $this->artisan('instance:update', [
        'instance' => 'inst-1',
        '--uses-octane' => 'true',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sent->body['uses_octane'])->toBeTrue();
});

it('sends --wildcard-enabled=false as false when creating a domain', function () {
    $this->artisan('domain:create', [
        'environment' => 'env-1',
        '--name' => 'example.com',
        '--wildcard-enabled' => 'false',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sent->body['wildcard_enabled'])->toBeFalse();
});
