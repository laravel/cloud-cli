<?php

use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Secrets\CreateSecretRequest;
use App\Client\Resources\Secrets\GetSecretPublicKeyRequest;
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

function setupSecretCreateMocks(): Closure
{
    $sentBody = new stdClass;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetSecretPublicKeyRequest::class => MockResponse::make(secretPublicKeyResponse(), 200),
        CreateSecretRequest::class => function (PendingRequest $request) use ($sentBody) {
            $sentBody->value = $request->body()->all();

            return MockResponse::make(['data' => secretResponse()], 201);
        },
    ]);

    return fn () => $sentBody->value ?? null;
}

it('creates a secret non-interactively', function () {
    $sentBody = setupSecretCreateMocks();

    $this->artisan('secret:create', [
        '--name' => 'STRIPE_KEY',
        '--value' => 'sk_test_123',
        '--notes' => 'Used by billing.',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->toMatchArray([
        'key_pair_id' => 'keypair-1',
        'key' => 'STRIPE_KEY',
        'notes' => 'Used by billing.',
    ]);
});

it('encrypts the value with the public key before sending it', function () {
    $sentBody = setupSecretCreateMocks();

    $this->artisan('secret:create', [
        '--name' => 'STRIPE_KEY',
        '--value' => 'sk_test_123',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody()['value'])->not->toBe('sk_test_123');
    expect(decryptSecretValue($sentBody()['value']))->toBe('sk_test_123');
});

it('omits notes when none are given', function () {
    $sentBody = setupSecretCreateMocks();

    $this->artisan('secret:create', [
        '--name' => 'STRIPE_KEY',
        '--value' => 'sk_test_123',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->not->toHaveKey('notes');
});

it('encrypts multi-line values', function () {
    $sentBody = setupSecretCreateMocks();

    $value = "-----BEGIN PRIVATE KEY-----\nline-one\nline-two\n-----END PRIVATE KEY-----";

    $this->artisan('secret:create', [
        '--name' => 'SSH_KEY',
        '--value' => $value,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(decryptSecretValue($sentBody()['value']))->toBe($value);
});
