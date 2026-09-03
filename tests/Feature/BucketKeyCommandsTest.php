<?php

use App\Client\Resources\BucketKeys\CreateBucketKeyRequest;
use App\Client\Resources\BucketKeys\GetBucketKeyRequest;
use App\Client\Resources\BucketKeys\ListBucketKeysRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\ObjectStorageBuckets\ListObjectStorageBucketsRequest;
use App\ConfigRepository;
use App\Dto\BucketKey;
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

function setupBucketKeyMocks(?array $key = null): void
{
    $key ??= bucketKeyResponse();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListObjectStorageBucketsRequest::class => MockResponse::make([
            'data' => [bucketResponse()],
            'links' => ['next' => null],
        ], 200),
        ListBucketKeysRequest::class => MockResponse::make([
            'data' => [$key],
            'links' => ['next' => null],
        ], 200),
        GetBucketKeyRequest::class => MockResponse::make(['data' => $key], 200),
        CreateBucketKeyRequest::class => MockResponse::make(['data' => $key], 201),
    ]);
}

it('maps the secret from the api access_key_secret attribute', function () {
    $key = BucketKey::createFromResponse(['data' => bucketKeyResponse()]);

    expect($key->accessKeyId)->toBe('AKIAEXAMPLE123');
    expect($key->secretAccessKey)->toBe('super-secret-value');
});

it('leaves the credentials null when the api withholds them', function () {
    $key = BucketKey::createFromResponse(['data' => bucketKeyResponse([
        'attributes' => ['access_key_id' => null, 'access_key_secret' => null],
    ])]);

    expect($key->accessKeyId)->toBeNull();
    expect($key->secretAccessKey)->toBeNull();
});

it('reveals the secret in bucket-key:get json with --show-sensitive', function () {
    setupBucketKeyMocks();

    $this->artisan('bucket-key:get', [
        'bucket' => 'fls-1',
        'key' => 'flsk-1',
        '--json' => true,
        '--show-sensitive' => true,
        '--no-interaction' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('super-secret-value');
});

it('masks the secret in bucket-key:get json by default', function () {
    setupBucketKeyMocks();

    $this->artisan('bucket-key:get', [
        'bucket' => 'fls-1',
        'key' => 'flsk-1',
        '--json' => true,
        '--no-interaction' => true,
    ])
        ->assertSuccessful()
        ->doesntExpectOutputToContain('super-secret-value')
        ->expectsOutputToContain('*****');
});

it('reveals the secret in bucket-key:create json with --show-sensitive', function () {
    setupBucketKeyMocks();

    $this->artisan('bucket-key:create', [
        'bucket' => 'fls-1',
        '--name' => 'my-key',
        '--permission' => 'read_write',
        '--json' => true,
        '--show-sensitive' => true,
        '--no-interaction' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('super-secret-value');
});

it('masks the secret in bucket-key:create json by default', function () {
    setupBucketKeyMocks();

    $this->artisan('bucket-key:create', [
        'bucket' => 'fls-1',
        '--name' => 'my-key',
        '--permission' => 'read_write',
        '--json' => true,
        '--no-interaction' => true,
    ])
        ->assertSuccessful()
        ->doesntExpectOutputToContain('super-secret-value')
        ->expectsOutputToContain('*****');
});
