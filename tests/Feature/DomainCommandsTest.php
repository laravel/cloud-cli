<?php

use App\Client\Resources\Domains\CreateDomainRequest;
use App\Client\Resources\Domains\GetDomainRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use App\Dto\Domain;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    $this->sent = new stdClass;

    $domain = ['data' => [
        'id' => 'domain-1',
        'type' => 'domains',
        'attributes' => [
            'name' => 'example.com',
            'type' => 'root',
            'stage' => 'pre_verification',
            'hostname_status' => 'pending',
            'ssl_status' => 'pending',
            'origin_status' => 'pending',
            'redirect' => null,
            'cloudflare_strategy' => 'dns_proxy',
            'downtime' => false,
            'wildcard_enabled' => true,
            'dns_records' => [],
            'wildcard' => null,
            'www' => null,
            'action_required' => 'add_txt_records',
            'last_verified_at' => null,
            'created_at' => '2026-01-01T00:00:00.000000Z',
        ],
        'relationships' => ['environment' => ['data' => ['id' => 'env-1', 'type' => 'environments']]],
    ]];

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()], 200),
        GetDomainRequest::class => MockResponse::make($domain, 200),
        CreateDomainRequest::class => function (PendingRequest $request) use ($domain) {
            $this->sent->body = $request->body()->all();

            return MockResponse::make($domain, 201);
        },
    ]);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

it('sends the cloudflare strategy and downtime options when creating a domain', function () {
    $this->artisan('domain:create', [
        'environment' => 'env-1',
        '--name' => 'example.com',
        '--cloudflare-strategy' => 'dns_proxy',
        '--allow-downtime' => 'false',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sent->body['cloudflare_strategy'])->toBe('dns_proxy');
    expect($this->sent->body['allow_downtime'])->toBeFalse();
});

it('leaves the cloudflare strategy and downtime to the API when not passed', function () {
    $this->artisan('domain:create', [
        'environment' => 'env-1',
        '--name' => 'example.com',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sent->body)->not->toHaveKey('cloudflare_strategy');
    expect($this->sent->body)->not->toHaveKey('allow_downtime');
});

it('includes the onboarding fields in the domain JSON', function () {
    Artisan::call('domain:get', [
        'domain' => 'domain-1',
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);

    expect($json)->toMatchArray([
        'stage' => 'pre_verification',
        'cloudflareStrategy' => 'dns_proxy',
        'downtime' => false,
        'wildcardEnabled' => true,
        'actionRequired' => 'add_txt_records',
    ]);
});

it('describes the action a domain needs', function (?string $action, ?string $label) {
    $domain = Domain::createFromResponse(['data' => [
        'id' => 'domain-1',
        'attributes' => ['name' => 'example.com', 'action_required' => $action],
    ]]);

    expect($domain->actionRequiredLabel())->toBe($label);
})->with([
    ['add_txt_records', 'Add the TXT records to verify ownership'],
    ['add_dns_records', 'Add the DNS records to point the domain at Laravel Cloud'],
    ['failed', 'Verification failed'],
    [null, null],
]);
