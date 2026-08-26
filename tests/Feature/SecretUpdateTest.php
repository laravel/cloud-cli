<?php

use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Secrets\GetSecretPublicKeyRequest;
use App\Client\Resources\Secrets\ListSecretsRequest;
use App\Client\Resources\Secrets\UpdateSecretRequest;
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

function setupSecretUpdateMocks(): Closure
{
    $sentBody = new stdClass;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListSecretsRequest::class => MockResponse::make([
            'data' => [secretResponse(['attributes' => ['notes' => 'Used by billing.']])],
            'links' => ['next' => null],
        ], 200),
        GetSecretPublicKeyRequest::class => MockResponse::make(secretPublicKeyResponse(), 200),
        UpdateSecretRequest::class => function (PendingRequest $request) use ($sentBody) {
            $sentBody->value = $request->body()->all();

            return MockResponse::make(['data' => secretResponse()], 200);
        },
    ]);

    return fn () => $sentBody->value ?? null;
}

it('updates the name and resends it as the key', function () {
    $sentBody = setupSecretUpdateMocks();

    $this->artisan('secret:update', [
        'secret' => 'secret-1',
        '--name' => 'STRIPE_SECRET',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->toMatchArray(['key' => 'STRIPE_SECRET']);
});

it('resolves a secret by name', function () {
    $sentBody = setupSecretUpdateMocks();

    $this->artisan('secret:update', [
        'secret' => 'STRIPE_KEY',
        '--notes' => 'Rotated quarterly.',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->toMatchArray(['notes' => 'Rotated quarterly.']);
});

it('sends the current name when only other fields change', function () {
    $sentBody = setupSecretUpdateMocks();

    $this->artisan('secret:update', [
        'secret' => 'secret-1',
        '--notes' => 'Rotated quarterly.',
        '--no-interaction' => true,
    ])->assertSuccessful();

    // The API requires the key on every update.
    expect($sentBody())->toMatchArray(['key' => 'STRIPE_KEY']);
});

it('encrypts a new value and sends the key pair it was encrypted with', function () {
    $sentBody = setupSecretUpdateMocks();

    $this->artisan('secret:update', [
        'secret' => 'secret-1',
        '--value' => 'sk_test_rotated',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->toMatchArray(['key_pair_id' => 'keypair-1']);
    expect(decryptSecretValue($sentBody()['value']))->toBe('sk_test_rotated');
});

it('omits the value and key pair when the value is unchanged', function () {
    $sentBody = setupSecretUpdateMocks();

    $this->artisan('secret:update', [
        'secret' => 'secret-1',
        '--name' => 'STRIPE_SECRET',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->not->toHaveKey('value');
    expect($sentBody())->not->toHaveKey('key_pair_id');
});

it('fails when no fields are given', function () {
    setupSecretUpdateMocks();

    $this->artisan('secret:update', [
        'secret' => 'secret-1',
        '--no-interaction' => true,
    ])->assertFailed();
});
