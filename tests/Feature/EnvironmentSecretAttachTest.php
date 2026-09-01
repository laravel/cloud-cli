<?php

use App\Client\Resources\Environments\AttachEnvironmentSecretsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Secrets\ListSecretsRequest;
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

function setupEnvironmentSecretAttachMocks(?array $secrets = null): Closure
{
    $sentBody = new stdClass;

    $secrets ??= [
        secretResponse(),
        secretResponse(['id' => 'secret-2', 'attributes' => ['key' => 'MAILGUN_SECRET']]),
    ];

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()], 200),
        ListSecretsRequest::class => MockResponse::make([
            'data' => $secrets,
            'links' => ['next' => null],
        ], 200),
        AttachEnvironmentSecretsRequest::class => function (PendingRequest $request) use ($sentBody) {
            $sentBody->value = $request->body()->all();
            $sentBody->url = $request->getUrl();

            return MockResponse::make(['data' => createEnvironmentResponse()], 200);
        },
    ]);

    return fn () => $sentBody;
}

it('attaches secrets by ID', function () {
    $sent = setupEnvironmentSecretAttachMocks();

    $this->artisan('environment-secret:attach', [
        'environment' => 'env-1',
        'secrets' => ['secret-1', 'secret-2'],
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent()->value)->toBe(['secrets' => ['secret-1', 'secret-2']]);
    expect($sent()->url)->toEndWith('/environments/env-1/secrets');
});

it('attaches a single secret', function () {
    $sent = setupEnvironmentSecretAttachMocks();

    $this->artisan('environment-secret:attach', [
        'environment' => 'env-1',
        'secrets' => ['secret-2'],
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent()->value)->toBe(['secrets' => ['secret-2']]);
});

it('sends a secret only once when it is repeated', function () {
    $sent = setupEnvironmentSecretAttachMocks();

    $this->artisan('environment-secret:attach', [
        'environment' => 'env-1',
        'secrets' => ['secret-1', 'secret-1'],
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent()->value)->toBe(['secrets' => ['secret-1']]);
});

it('rejects a secret name', function () {
    $sent = setupEnvironmentSecretAttachMocks();

    $this->artisan('environment-secret:attach', [
        'environment' => 'env-1',
        // Names are not unique, so only IDs are accepted.
        'secrets' => ['STRIPE_KEY'],
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent()->value ?? null)->toBeNull();
});

it('fails when no secrets are given non-interactively', function () {
    $sent = setupEnvironmentSecretAttachMocks();

    $this->artisan('environment-secret:attach', [
        'environment' => 'env-1',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent()->value ?? null)->toBeNull();
});

it('fails when the organization has no secrets', function () {
    $sent = setupEnvironmentSecretAttachMocks([]);

    $this->artisan('environment-secret:attach', [
        'environment' => 'env-1',
        'secrets' => ['secret-1'],
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent()->value ?? null)->toBeNull();
});
