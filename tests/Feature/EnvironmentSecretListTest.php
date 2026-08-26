<?php

use App\Client\Resources\Environments\GetEnvironmentRequest;
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

function setupEnvironmentSecretListMocks(array $secrets): Closure
{
    $requested = new stdClass;

    $environment = createEnvironmentResponse([
        'relationships' => [
            'secrets' => ['data' => array_map(fn (array $secret) => [
                'id' => $secret['id'],
                'type' => 'secrets',
            ], $secrets)],
        ],
    ]);

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => function (PendingRequest $request) use ($environment, $secrets, $requested) {
            $requested->includes[] = $request->query()->get('include');

            return MockResponse::make(['data' => $environment, 'included' => $secrets], 200);
        },
    ]);

    return fn () => $requested->includes ?? [];
}

it('lists the secrets attached to an environment', function () {
    setupEnvironmentSecretListMocks([
        secretResponse(['attributes' => ['notes' => 'Used by billing.']]),
        secretResponse(['id' => 'secret-2', 'attributes' => ['key' => 'MAILGUN_SECRET']]),
    ]);

    $this->artisan('environment-secret:list', [
        'environment' => 'env-1',
        '--no-interaction' => true,
    ])->assertSuccessful()->expectsOutputToContain('MAILGUN_SECRET');
});

it('requests the secrets include', function () {
    $includes = setupEnvironmentSecretListMocks([secretResponse()]);

    $this->artisan('environment-secret:list', [
        'environment' => 'env-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($includes())->toContain('secrets');
});

it('warns when no secrets are attached', function () {
    setupEnvironmentSecretListMocks([]);

    $this->artisan('environment-secret:list', [
        'environment' => 'env-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});
