<?php

use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
use App\Client\Resources\ObjectStorageBuckets\CreateObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\GetObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\ListObjectStorageBucketsRequest;
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

    $usBucket = bucketResponse(['id' => 'fls-us', 'attributes' => ['name' => 'us-bucket', 'jurisdiction' => 'us']]);

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
        ListObjectStorageBucketsRequest::class => MockResponse::make([
            'data' => [bucketResponse(), $usBucket],
            'links' => ['next' => null],
        ], 200),
        GetObjectStorageBucketRequest::class => MockResponse::make(['data' => $usBucket], 200),
        CreateObjectStorageBucketRequest::class => function (PendingRequest $request) use ($usBucket) {
            $this->sent->body = $request->body()->all();

            return MockResponse::make(['data' => $usBucket], 201);
        },
    ]);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

it('lists buckets when one is in the us jurisdiction', function () {
    Artisan::call('bucket:list', ['--json' => true]);

    expect(collect(json_decode(Artisan::output(), true))->pluck('jurisdiction')->all())->toBe(['default', 'us']);
});

it('gets a bucket in the us jurisdiction', function () {
    $this->artisan('bucket:get', ['bucket' => 'fls-us', '--no-interaction' => true])->assertSuccessful();
});

it('creates a bucket in the us jurisdiction', function () {
    $this->artisan('bucket:create', [
        '--name' => 'us-bucket',
        '--region' => 'us-east-2',
        '--visibility' => 'private',
        '--jurisdiction' => 'us',
        '--key-name' => 'us-key',
        '--key-permission' => 'read_write',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sent->body['jurisdiction'])->toBe('us');
});

it('creates a bucket non-interactively without allowed origins', function () {
    $this->artisan('bucket:create', [
        '--name' => 'us-bucket',
        '--key-name' => 'us-key',
        '--key-permission' => 'read_write',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sent->body)->not->toHaveKey('allowed_origins')
        ->and($this->sent->body['jurisdiction'])->toBe('default');
});
