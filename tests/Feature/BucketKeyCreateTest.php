<?php

use App\Client\Resources\BucketKeys\CreateBucketKeyRequest;
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

function bkCreateBucketResponse(): array
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

function bkCreateKeyResponse(array $overrides = []): array
{
    return array_merge([
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
    ], $overrides);
}

// ---- Create key successfully ----

it('creates a bucket key with name and default permission', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bkCreateBucketResponse(), 200),
        CreateBucketKeyRequest::class => MockResponse::make(bkCreateKeyResponse(), 200),
    ]);

    $this->artisan('bucket-key:create', [
        'bucket' => 'fls-bucket-1',
        '--name' => 'my-key',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('creates a bucket key with explicit permission', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bkCreateBucketResponse(), 200),
        CreateBucketKeyRequest::class => MockResponse::make(
            bkCreateKeyResponse(),
            200,
        ),
    ]);

    $this->artisan('bucket-key:create', [
        'bucket' => 'fls-bucket-1',
        '--name' => 'my-key',
        '--permission' => 'read_only',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('creates a bucket key with --json output', function () {
    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bkCreateBucketResponse(), 200),
        CreateBucketKeyRequest::class => MockResponse::make(bkCreateKeyResponse(), 200),
    ]);

    $this->artisan('bucket-key:create', [
        'bucket' => 'fls-bucket-1',
        '--name' => 'my-key',
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"id"');
});

// ---- Resolve bucket by name ----

it('creates key resolving bucket by name', function () {
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
        CreateBucketKeyRequest::class => MockResponse::make(bkCreateKeyResponse(), 200),
    ]);

    $this->artisan('bucket-key:create', [
        'bucket' => 'my-bucket',
        '--name' => 'my-key',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

// ---- Bucket not found ----

it('fails when bucket not found', function () {
    Prompt::fake();

    MockClient::global([
        ListObjectStorageBucketsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('bucket-key:create', [
        'bucket' => 'nonexistent',
        '--name' => 'my-key',
        '--no-interaction' => true,
    ])->assertFailed();
});

// ---- API validation error ----

it('handles validation error on create', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bkCreateBucketResponse(), 200),
        CreateBucketKeyRequest::class => MockResponse::make([
            'message' => 'Validation failed',
            'errors' => ['name' => ['Name has already been taken.']],
        ], 422),
    ]);

    // loopUntilValid would normally loop, but in non-interactive mode
    // it will throw on the second attempt since it can't re-prompt
    $this->artisan('bucket-key:create', [
        'bucket' => 'fls-bucket-1',
        '--name' => 'taken-name',
        '--no-interaction' => true,
    ])->assertFailed();
});
