<?php

use App\Client\Resources\Domains\ListDomainsRequest;
use App\Client\Resources\Environments\ClearPrimaryDomainRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\UpdatePrimaryDomainRequest;
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

function primaryDomainResponse(string $id, string $name, string $type = 'root'): array
{
    return [
        'id' => $id,
        'type' => 'domains',
        'attributes' => [
            'name' => $name,
            'type' => $type,
            'hostname_status' => 'verified',
            'ssl_status' => 'verified',
            'origin_status' => 'verified',
            'redirect' => null,
        ],
    ];
}

/**
 * @return Closure(): stdClass the primary domain requests that were sent, if any
 */
function setupPrimaryDomainMocks(?string $currentPrimaryId = null): Closure
{
    $sent = new stdClass;
    $sent->primaryId = $currentPrimaryId;

    $environment = function () use ($sent) {
        if (! $sent->primaryId) {
            return MockResponse::make(['data' => createEnvironmentResponse()], 200);
        }

        $domain = $sent->primaryId === 'domain-2'
            ? primaryDomainResponse('domain-2', 'shop.example.com')
            : primaryDomainResponse('domain-1', 'example.com');

        return MockResponse::make([
            'data' => createEnvironmentResponse([
                'relationships' => ['primaryDomain' => ['data' => ['id' => $sent->primaryId, 'type' => 'domains']]],
            ]),
            'included' => [$domain],
        ], 200);
    };

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => $environment,
        ListDomainsRequest::class => MockResponse::make(['data' => [
            primaryDomainResponse('domain-1', 'example.com'),
            primaryDomainResponse('domain-2', 'shop.example.com'),
        ]], 200),
        UpdatePrimaryDomainRequest::class => function (PendingRequest $request) use ($sent) {
            $sent->updateUrl = $request->getUrl();
            $sent->updateMethod = $request->getMethod()->value;
            $sent->body = $request->body()?->all();
            $sent->primaryId = $sent->body['domain_id'];

            return MockResponse::make(['data' => createEnvironmentResponse()], 200);
        },
        ClearPrimaryDomainRequest::class => function (PendingRequest $request) use ($sent) {
            $sent->clearUrl = $request->getUrl();
            $sent->clearMethod = $request->getMethod()->value;
            $sent->primaryId = null;

            return MockResponse::make([], 204);
        },
    ]);

    return fn () => $sent;
}

it('sets the primary domain by name', function () {
    $sent = setupPrimaryDomainMocks();

    $this->artisan('environment:primary-domain', [
        'environment' => 'env-1',
        '--domain' => 'shop.example.com',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent()->updateMethod)->toBe('PUT');
    expect($sent()->updateUrl)->toEndWith('/environments/env-1/primary-domain');
    expect($sent()->body)->toBe(['domain_id' => 'domain-2']);
});

it('sets the primary domain by id', function () {
    $sent = setupPrimaryDomainMocks();

    $this->artisan('environment:primary-domain', [
        'environment' => 'env-1',
        '--domain' => 'domain-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent()->body)->toBe(['domain_id' => 'domain-1']);
});

it('outputs the environment with its new primary domain as JSON', function () {
    setupPrimaryDomainMocks();

    Artisan::call('environment:primary-domain', [
        'environment' => 'env-1',
        '--domain' => 'example.com',
        '--json' => true,
    ]);

    expect(json_decode(Artisan::output(), true))->toMatchArray([
        'id' => 'env-1',
        'primaryDomainId' => 'domain-1',
    ]);
});

it('does nothing when the domain is already primary', function () {
    $sent = setupPrimaryDomainMocks('domain-1');

    $this->artisan('environment:primary-domain', [
        'environment' => 'env-1',
        '--domain' => 'example.com',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent()->updateUrl ?? null)->toBeNull();
});

it('fails when the domain is not attached to the environment', function () {
    $sent = setupPrimaryDomainMocks();

    $this->artisan('environment:primary-domain', [
        'environment' => 'env-1',
        '--domain' => 'other.com',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent()->updateUrl ?? null)->toBeNull();
});

it('requires a domain or --clear when run non-interactively', function () {
    $sent = setupPrimaryDomainMocks();

    $this->artisan('environment:primary-domain', [
        'environment' => 'env-1',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent()->updateUrl ?? null)->toBeNull();
});

it('refuses --domain together with --clear', function () {
    $sent = setupPrimaryDomainMocks('domain-1');

    $this->artisan('environment:primary-domain', [
        'environment' => 'env-1',
        '--domain' => 'example.com',
        '--clear' => true,
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent()->updateUrl ?? null)->toBeNull();
    expect($sent()->clearUrl ?? null)->toBeNull();
});

it('clears the primary domain', function () {
    $sent = setupPrimaryDomainMocks('domain-1');

    Artisan::call('environment:primary-domain', [
        'environment' => 'env-1',
        '--clear' => true,
        '--json' => true,
    ]);

    expect($sent()->clearMethod)->toBe('DELETE');
    expect($sent()->clearUrl)->toEndWith('/environments/env-1/primary-domain');
    expect(json_decode(Artisan::output(), true)['primaryDomainId'])->toBeNull();
});

it('does not clear when there is no primary domain', function () {
    $sent = setupPrimaryDomainMocks();

    $this->artisan('environment:primary-domain', [
        'environment' => 'env-1',
        '--clear' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent()->clearUrl ?? null)->toBeNull();
});
