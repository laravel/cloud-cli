<?php

use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Secrets\DeleteSecretRequest;
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

function setupSecretDeleteMocks(): Closure
{
    $deleted = new stdClass;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListSecretsRequest::class => MockResponse::make([
            'data' => [
                secretResponse(),
                secretResponse(['id' => 'secret-2', 'attributes' => ['key' => 'MAILGUN_SECRET']]),
            ],
            'links' => ['next' => null],
        ], 200),
        DeleteSecretRequest::class => function (PendingRequest $request) use ($deleted) {
            $deleted->url = $request->getUrl();

            return MockResponse::make('', 204);
        },
    ]);

    return fn () => $deleted->url ?? null;
}

it('deletes a secret by ID with --force', function () {
    $deletedUrl = setupSecretDeleteMocks();

    $this->artisan('secret:delete', [
        'secret' => 'secret-2',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($deletedUrl())->toEndWith('/secrets/secret-2');
});

it('rejects a secret name', function () {
    $deletedUrl = setupSecretDeleteMocks();

    // Names are not unique, so only IDs are accepted.
    $this->artisan('secret:delete', [
        'secret' => 'MAILGUN_SECRET',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();

    expect($deletedUrl())->toBeNull();
});

it('requires --force when non-interactive', function () {
    $deletedUrl = setupSecretDeleteMocks();

    $this->artisan('secret:delete', [
        'secret' => 'secret-2',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($deletedUrl())->toBeNull();
});
