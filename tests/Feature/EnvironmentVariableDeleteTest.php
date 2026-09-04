<?php

use App\Client\Resources\Environments\AddEnvironmentVariablesRequest;
use App\Client\Resources\Environments\DeleteEnvironmentVariablesRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
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

function setupEnvironmentVariableMocks(): void
{
    $environment = createEnvironmentResponse([
        'attributes' => [
            'environment_variables' => [
                ['key' => 'STRIPE_KEY', 'value' => 'sk_test'],
                ['key' => 'MAIL_FROM', 'value' => 'hi@example.com'],
            ],
        ],
    ]);

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => $environment], 200),
        AddEnvironmentVariablesRequest::class => MockResponse::make(['data' => $environment], 200),
        DeleteEnvironmentVariablesRequest::class => MockResponse::make(['data' => $environment], 200),
    ]);
}

it('deletes a variable at the canonical endpoint', function () {
    setupEnvironmentVariableMocks();

    $this->artisan('environment:variables', [
        'environment' => 'env-1',
        '--action' => 'delete',
        '--key' => 'STRIPE_KEY',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof DeleteEnvironmentVariablesRequest
            && $request->resolveEndpoint() === '/environments/env-1/variables/delete'
            && $request->body()->all() === ['keys' => ['STRIPE_KEY']];
    });
});

it('deletes several variables from a comma-separated list', function () {
    setupEnvironmentVariableMocks();

    $this->artisan('environment:variables', [
        'environment' => 'env-1',
        '--action' => 'delete',
        '--key' => 'STRIPE_KEY, MAIL_FROM',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof DeleteEnvironmentVariablesRequest
            && $request->body()->all() === ['keys' => ['STRIPE_KEY', 'MAIL_FROM']];
    });
});

it('refuses to delete without --force when non-interactive', function () {
    setupEnvironmentVariableMocks();

    $this->artisan('environment:variables', [
        'environment' => 'env-1',
        '--action' => 'delete',
        '--key' => 'STRIPE_KEY',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('fails when no keys are given', function () {
    setupEnvironmentVariableMocks();

    $this->artisan('environment:variables', [
        'environment' => 'env-1',
        '--action' => 'delete',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();
});

it('rejects the removed replace action', function () {
    setupEnvironmentVariableMocks();

    $this->artisan('environment:variables', [
        'environment' => 'env-1',
        '--action' => 'replace',
        '--key' => 'STRIPE_KEY',
        '--value' => 'sk_live',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();
});

it('still appends variables', function () {
    setupEnvironmentVariableMocks();

    $this->artisan('environment:variables', [
        'environment' => 'env-1',
        '--action' => 'append',
        '--key' => 'NEW_KEY',
        '--value' => 'new-value',
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof AddEnvironmentVariablesRequest
            && $request->body()->all() === [
                'method' => 'append',
                'variables' => [['key' => 'NEW_KEY', 'value' => 'new-value']],
            ];
    });
});
