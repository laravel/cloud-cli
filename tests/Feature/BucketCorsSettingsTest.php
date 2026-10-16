<?php

use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\ObjectStorageBuckets\CreateObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\GetObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\ListObjectStorageBucketsRequest;
use App\Client\Resources\ObjectStorageBuckets\UpdateObjectStorageBucketRequest;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    $this->sent = new stdClass;

    $this->bucket = bucketResponse([
        'attributes' => [
            'location_hint' => 'weur',
            'cors_settings' => [
                'allowed_origins' => ['https://example.com'],
                'allowed_methods' => ['GET', 'HEAD'],
                'allowed_headers' => ['*'],
                'expose_headers' => [],
                'max_age_seconds' => 3600,
            ],
        ],
    ]);

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListObjectStorageBucketsRequest::class => MockResponse::make([
            'data' => [$this->bucket],
            'links' => ['next' => null],
        ], 200),
        GetObjectStorageBucketRequest::class => MockResponse::make(['data' => $this->bucket], 200),
        CreateObjectStorageBucketRequest::class => function (PendingRequest $request) {
            $this->sent->body = $request->body()->all();

            return MockResponse::make(['data' => $this->bucket], 201);
        },
        UpdateObjectStorageBucketRequest::class => function (PendingRequest $request) {
            $this->sent->body = $request->body()->all();

            return MockResponse::make(['data' => $this->bucket], 200);
        },
    ]);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function createBucket(array $options = []): void
{
    test()->artisan('bucket:create', array_merge([
        '--name' => 'my-bucket',
        '--key-name' => 'my-key',
        '--key-permission' => 'read_write',
        '--no-interaction' => true,
    ], $options))->assertSuccessful();
}

it('sends allowed origins inside cors_settings instead of the deprecated field', function () {
    createBucket(['--allowed-origins' => 'https://example.com, https://app.example.com']);

    expect($this->sent->body)->not->toHaveKey('allowed_origins')
        ->and($this->sent->body['cors_settings'])->toBe([
            'allowed_origins' => ['https://example.com', 'https://app.example.com'],
        ]);
});

it('sends every cors setting on create', function () {
    createBucket([
        '--allowed-origins' => 'https://example.com',
        '--allowed-methods' => 'get,head',
        '--allowed-headers' => 'Content-Type,Authorization',
        '--expose-headers' => 'ETag',
        '--max-age-seconds' => '3600',
    ]);

    expect($this->sent->body['cors_settings'])->toBe([
        'allowed_origins' => ['https://example.com'],
        'allowed_methods' => ['GET', 'HEAD'],
        'allowed_headers' => ['Content-Type', 'Authorization'],
        'expose_headers' => ['ETag'],
        'max_age_seconds' => 3600,
    ]);
});

it('leaves cors_settings out on create when none are given', function () {
    createBucket();

    expect($this->sent->body)->not->toHaveKey('cors_settings');
});

it('defaults the location hint to automatic for the default jurisdiction', function () {
    createBucket();

    expect($this->sent->body['location_hint'])->toBe('automatic');
});

it('sends the location hint option on create', function () {
    createBucket(['--location-hint' => 'weur']);

    expect($this->sent->body['location_hint'])->toBe('weur');
});

it('leaves the location hint out when a jurisdiction is set', function () {
    createBucket(['--jurisdiction' => 'eu']);

    expect($this->sent->body)->not->toHaveKey('location_hint');
});

it('passes an explicit location hint through with a jurisdiction for the api to validate', function () {
    createBucket(['--jurisdiction' => 'eu', '--location-hint' => 'automatic']);

    expect($this->sent->body['location_hint'])->toBe('automatic');
});

it('sends only the changed cors setting on update', function () {
    $this->artisan('bucket:update', [
        'bucket' => 'fls-1',
        '--allowed-origins' => 'https://new.example.com',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sent->body)->toBe([
        'cors_settings' => ['allowed_origins' => ['https://new.example.com']],
    ]);
});

it('clears allowed origins on update when given an empty list', function () {
    $this->artisan('bucket:update', [
        'bucket' => 'fls-1',
        '--allowed-origins' => '',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sent->body)->toBe([
        'cors_settings' => ['allowed_origins' => []],
    ]);
});

it('sends methods and max age on update', function () {
    $this->artisan('bucket:update', [
        'bucket' => 'fls-1',
        '--allowed-methods' => 'get,put',
        '--max-age-seconds' => '600',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sent->body)->toBe([
        'cors_settings' => [
            'allowed_methods' => ['GET', 'PUT'],
            'max_age_seconds' => 600,
        ],
    ]);
});

it('leaves cors_settings out of an update that does not touch it', function () {
    $this->artisan('bucket:update', [
        'bucket' => 'fls-1',
        '--visibility' => 'public',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sent->body)->toBe(['visibility' => 'public']);
});

it('includes the location hint and cors settings in bucket:get json', function () {
    Artisan::call('bucket:get', ['bucket' => 'fls-1', '--json' => true, '--no-interaction' => true]);

    $output = json_decode(Artisan::output(), true);

    expect($output['locationHint'])->toBe('weur')
        ->and($output['corsSettings'])->toBe($this->bucket['attributes']['cors_settings']);
});
