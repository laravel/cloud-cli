<?php

use App\Client\Resources\BucketKeys\DeleteBucketKeyRequest;
use App\Client\Resources\BucketKeys\ListBucketKeysRequest;
use App\Client\Resources\ObjectStorageBuckets\DeleteObjectStorageBucketRequest;
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

function bucketDeleteGetResponse(): array
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
            'relationships' => [
                'keys' => ['data' => []],
            ],
        ],
    ];
}

it('deletes a bucket with force flag and no keys', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bucketDeleteGetResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
        DeleteObjectStorageBucketRequest::class => MockResponse::make([], 200),
    ]);

    $this->artisan('bucket:delete', [
        'bucket' => 'fls-bucket-1',
        '--force' => true,
    ])->assertSuccessful();
});

it('deletes a bucket with keys', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bucketDeleteGetResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make([
            'data' => [
                [
                    'id' => 'key-1',
                    'type' => 'bucketKeys',
                    'attributes' => [
                        'name' => 'my-key',
                        'permission' => 'read_write',
                        'created_at' => now()->toISOString(),
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),
        DeleteBucketKeyRequest::class => MockResponse::make([], 200),
        DeleteObjectStorageBucketRequest::class => MockResponse::make([], 200),
    ]);

    $this->artisan('bucket:delete', [
        'bucket' => 'fls-bucket-1',
        '--force' => true,
    ])->assertSuccessful();
});

it('resolves bucket by name', function () {
    Prompt::fake();

    MockClient::global([
        ListObjectStorageBucketsRequest::class => MockResponse::make([
            'data' => [
                [
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
                    'relationships' => ['keys' => ['data' => []]],
                ],
            ],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        ListBucketKeysRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
        DeleteObjectStorageBucketRequest::class => MockResponse::make([], 200),
    ]);

    $this->artisan('bucket:delete', [
        'bucket' => 'my-bucket',
        '--force' => true,
    ])->assertSuccessful();
});

it('fails when no buckets found', function () {
    Prompt::fake();

    MockClient::global([
        ListObjectStorageBucketsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('bucket:delete', [
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();
});

it('cancels deletion without force in non-interactive mode (uses default confirm=false)', function () {
    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bucketDeleteGetResponse(), 200),
    ]);

    // Without --force in non-interactive mode, confirm() uses its default (false),
    // so the command returns FAILURE (cancelled)
    $this->artisan('bucket:delete', [
        'bucket' => 'fls-bucket-1',
        '--no-interaction' => true,
    ])->assertFailed();
});
