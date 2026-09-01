<?php

use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Secrets\ListSecretsRequest;
use App\ConfigRepository;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function setupSecretListMocks(?array $secrets = null, int $status = 200): void
{
    $secrets ??= [
        secretResponse(['attributes' => ['notes' => 'Used by billing.']]),
        secretResponse(['id' => 'secret-2', 'attributes' => ['key' => 'MAILGUN_SECRET']]),
    ];

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListSecretsRequest::class => MockResponse::make([
            'data' => $secrets,
            'links' => ['next' => null],
        ], $status),
    ]);
}

it('lists secrets', function () {
    setupSecretListMocks();

    // One substring per run: several expectsOutputToContain() calls cannot all match a single write.
    $this->artisan('secret:list', ['--no-interaction' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('STRIPE_KEY');

    $this->artisan('secret:list', ['--no-interaction' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('MAILGUN_SECRET');
});

it('warns when there are no secrets', function () {
    setupSecretListMocks([]);

    $this->artisan('secret:list', ['--no-interaction' => true])->assertSuccessful();
});
