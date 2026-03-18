<?php

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

function bkListBucketResponse(): array
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
            'relationships' => ['keys' => ['data' => []]],
        ],
    ];
}

function bkListKeyItemResponse(array $overrides = []): array
{
    return array_merge([
        'id' => 'flsk-key-1',
        'type' => 'bucketKeys',
        'attributes' => [
            'name' => 'my-key',
            'permission' => 'read_write',
            'created_at' => now()->toISOString(),
        ],
    ], $overrides);
}

// ---- List keys successfully ----

it('lists bucket keys by bucket ID', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bkListBucketResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make([
            'data' => [bkListKeyItemResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('bucket-key:list', [
        'bucket' => 'fls-bucket-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('lists bucket keys with --json output', function () {
    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bkListBucketResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make([
            'data' => [bkListKeyItemResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('bucket-key:list', [
        'bucket' => 'fls-bucket-1',
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"id"');
});

// ---- Empty list ----

it('returns failure when no keys found in interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bkListBucketResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    // Empty list now returns failure
    $this->artisan('bucket-key:list', [
        'bucket' => 'fls-bucket-1',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('outputs empty JSON when no keys found with --json', function () {
    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bkListBucketResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    // Empty list now returns failure
    $this->artisan('bucket-key:list', [
        'bucket' => 'fls-bucket-1',
        '--json' => true,
    ])->assertFailed();
});

// ---- Multiple keys ----

it('lists multiple bucket keys', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bkListBucketResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make([
            'data' => [
                bkListKeyItemResponse(),
                bkListKeyItemResponse([
                    'id' => 'flsk-key-2',
                    'attributes' => [
                        'name' => 'read-only-key',
                        'permission' => 'read_only',
                        'created_at' => now()->toISOString(),
                    ],
                ]),
            ],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('bucket-key:list', [
        'bucket' => 'fls-bucket-1',
    ])->assertSuccessful();
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

    $this->artisan('bucket-key:list', [
        'bucket' => 'fls-nonexistent',
        '--no-interaction' => true,
    ])->assertFailed();
});

// ---- Resolve bucket by name ----

it('lists keys resolving bucket by name', function () {
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
            'data' => [bkListKeyItemResponse()],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('bucket-key:list', [
        'bucket' => 'my-bucket',
        '--no-interaction' => true,
    ])->assertSuccessful();
});
