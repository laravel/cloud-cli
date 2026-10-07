<?php

use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\UpdateVanityDomainRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
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

/**
 * @return Closure(): stdClass the vanity domain request that was sent, if any
 */
function setupVanityDomainMocks(?MockResponse $response = null): Closure
{
    $sent = new stdClass;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()], 200),
        UpdateVanityDomainRequest::class => function (PendingRequest $request) use ($sent, $response) {
            $sent->url = $request->getUrl();
            $sent->body = $request->body()?->all();

            return $response ?? MockResponse::make([
                'data' => createEnvironmentResponse(['attributes' => ['vanity_domain' => 'new-name.cloud.laravel.com']]),
            ], 200);
        },
    ]);

    return fn () => $sent;
}

it('sends only the new subdomain', function () {
    $sent = setupVanityDomainMocks();

    $this->artisan('environment:vanity-domain', [
        'environment' => 'env-1',
        '--name' => 'new-name',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent()->url)->toEndWith('/environments/env-1/vanity-domain');
    expect($sent()->body)->toBe(['name' => 'new-name']);
});

it('outputs the environment with its new domain as JSON', function () {
    setupVanityDomainMocks();

    Artisan::call('environment:vanity-domain', [
        'environment' => 'env-1',
        '--name' => 'new-name',
        '--force' => true,
        '--json' => true,
    ]);

    expect(json_decode(Artisan::output(), true))->toMatchArray([
        'vanityDomain' => 'new-name.cloud.laravel.com',
        'url' => 'https://new-name.cloud.laravel.com',
    ]);
});

it('does nothing when the name is unchanged', function () {
    $sent = setupVanityDomainMocks();

    $this->artisan('environment:vanity-domain', [
        'environment' => 'env-1',
        '--name' => 'my-app',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent()->url ?? null)->toBeNull();
});

it('requires --force when run non-interactively', function () {
    $sent = setupVanityDomainMocks();

    $this->artisan('environment:vanity-domain', [
        'environment' => 'env-1',
        '--name' => 'new-name',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent()->url ?? null)->toBeNull();
});

it('requires a name when run non-interactively', function () {
    $sent = setupVanityDomainMocks();

    $this->artisan('environment:vanity-domain', [
        'environment' => 'env-1',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent()->url ?? null)->toBeNull();
});

it('fails when the domain was changed in the last 30 minutes', function () {
    setupVanityDomainMocks(MockResponse::make(
        ['message' => 'The vanity domain may only be updated once every 30 minutes.'],
        429,
        ['Retry-After' => '600'],
    ));

    $this->artisan('environment:vanity-domain', [
        'environment' => 'env-1',
        '--name' => 'new-name',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();
});
