<?php

use App\Client\Resources\BucketKeys\GetBucketKeyRequest;
use App\Client\Resources\BucketKeys\ListBucketKeysRequest;
use App\Client\Resources\ObjectStorageBuckets\GetObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\ListObjectStorageBucketsRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Sleep::fake();

    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->mockGit->shouldReceive('currentBranch')->andReturn('main')->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('')->byDefault();
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(false)->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(fn () => MockClient::destroyGlobal());

function bkGetBucketResponse(): array
{
    return [
        'data' => [
            'id' => 'fls-bucket-1',
            'type' => 'objectStorageBuckets',
            'attributes' => [
                'name' => 'my-bucket',
                'type' => 'cloudflare_r2',
                'status' => 'available',
                'visibility' => 'private',
                'jurisdiction' => 'default',
                'endpoint' => 'https://example.com',
                'url' => 'https://example.com/my-bucket',
                'allowed_origins' => null,
                'created_at' => now()->toISOString(),
            ],
            'relationships' => ['keys' => ['data' => [['id' => 'flsk-key-1', 'type' => 'bucketKeys']]]],
        ],
    ];
}

function bkGetKeyListResponse(): array
{
    return [
        'data' => [
            [
                'id' => 'flsk-key-1',
                'type' => 'bucketKeys',
                'attributes' => [
                    'name' => 'my-key',
                    'permission' => 'read_write',
                    'created_at' => now()->toISOString(),
                ],
            ],
        ],
        'links' => ['next' => null],
    ];
}

function bkGetKeyDetailResponse(): array
{
    return [
        'data' => [
            'id' => 'flsk-key-1',
            'type' => 'bucketKeys',
            'attributes' => [
                'name' => 'my-key',
                'permission' => 'read_write',
                'access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
                'secret_access_key' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
                'created_at' => now()->toISOString(),
            ],
        ],
    ];
}

// ---- Get by ID ----

it('gets bucket key by ID successfully', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bkGetBucketResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make(bkGetKeyListResponse(), 200),
        GetBucketKeyRequest::class => MockResponse::make(bkGetKeyDetailResponse(), 200),
    ]);

    $this->artisan('bucket-key:get', [
        'bucket' => 'fls-bucket-1',
        'key' => 'flsk-key-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('gets bucket key by ID with --json output', function () {
    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bkGetBucketResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make(bkGetKeyListResponse(), 200),
        GetBucketKeyRequest::class => MockResponse::make(bkGetKeyDetailResponse(), 200),
    ]);

    $this->artisan('bucket-key:get', [
        'bucket' => 'fls-bucket-1',
        'key' => 'flsk-key-1',
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"id"');
});

// ---- Get by name ----

it('gets bucket key by name', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bkGetBucketResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make(bkGetKeyListResponse(), 200),
        GetBucketKeyRequest::class => MockResponse::make(bkGetKeyDetailResponse(), 200),
    ]);

    $this->artisan('bucket-key:get', [
        'bucket' => 'fls-bucket-1',
        'key' => 'my-key',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

// ---- Auto-select single key ----

it('auto-selects when only one key exists and no key argument given', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bkGetBucketResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make(bkGetKeyListResponse(), 200),
        GetBucketKeyRequest::class => MockResponse::make(bkGetKeyDetailResponse(), 200),
    ]);

    $this->artisan('bucket-key:get', [
        'bucket' => 'fls-bucket-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

// ---- Key not found ----

it('fails when key not found', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bkGetBucketResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('bucket-key:get', [
        'bucket' => 'fls-bucket-1',
        'key' => 'flsk-nonexistent',
        '--no-interaction' => true,
    ])->assertFailed();
});

// ---- Bucket not found ----

it('fails when bucket not found', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(['message' => 'Not found'], 404),
        ListObjectStorageBucketsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('bucket-key:get', [
        'bucket' => 'fls-nonexistent',
        'key' => 'flsk-key-1',
        '--no-interaction' => true,
    ])->assertFailed();
});
