<?php

use App\Client\Resources\ObjectStorageBuckets\GetObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\ListObjectStorageBucketsRequest;
use App\Client\Resources\ObjectStorageBuckets\UpdateObjectStorageBucketRequest;
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

function bucketUpdateGetResponse(array $overrides = []): array
{
    $base = [
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

    if (isset($overrides['attributes'])) {
        $base['data']['attributes'] = array_merge($base['data']['attributes'], $overrides['attributes']);
    }

    return $base;
}

// ---- Update with --force ----

it('updates bucket name with --force', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bucketUpdateGetResponse(), 200),
        UpdateObjectStorageBucketRequest::class => MockResponse::make(
            bucketUpdateGetResponse(['attributes' => ['name' => 'new-name']]),
            200,
        ),
    ]);

    $this->artisan('bucket:update', [
        'bucket' => 'fls-bucket-1',
        '--name' => 'new-name',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('updates bucket visibility with --force', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bucketUpdateGetResponse(), 200),
        UpdateObjectStorageBucketRequest::class => MockResponse::make(
            bucketUpdateGetResponse(['attributes' => ['visibility' => 'public']]),
            200,
        ),
    ]);

    $this->artisan('bucket:update', [
        'bucket' => 'fls-bucket-1',
        '--visibility' => 'public',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('updates multiple fields at once with --force', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bucketUpdateGetResponse(), 200),
        UpdateObjectStorageBucketRequest::class => MockResponse::make(
            bucketUpdateGetResponse(['attributes' => ['name' => 'new-name', 'visibility' => 'public']]),
            200,
        ),
    ]);

    $this->artisan('bucket:update', [
        'bucket' => 'fls-bucket-1',
        '--name' => 'new-name',
        '--visibility' => 'public',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('updates bucket with --json output', function () {
    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bucketUpdateGetResponse(), 200),
        UpdateObjectStorageBucketRequest::class => MockResponse::make(
            bucketUpdateGetResponse(['attributes' => ['name' => 'new-name']]),
            200,
        ),
    ]);

    $this->artisan('bucket:update', [
        'bucket' => 'fls-bucket-1',
        '--name' => 'new-name',
        '--force' => true,
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"id"');
});

// ---- By name ----

it('updates bucket resolved by name', function () {
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
        GetObjectStorageBucketRequest::class => MockResponse::make(bucketUpdateGetResponse(), 200),
        UpdateObjectStorageBucketRequest::class => MockResponse::make(
            bucketUpdateGetResponse(['attributes' => ['name' => 'renamed-bucket']]),
            200,
        ),
    ]);

    $this->artisan('bucket:update', [
        'bucket' => 'my-bucket',
        '--name' => 'renamed-bucket',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

// ---- No fields ----

it('fails when no fields provided in non-interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bucketUpdateGetResponse(), 200),
    ]);

    $this->artisan('bucket:update', [
        'bucket' => 'fls-bucket-1',
        '--no-interaction' => true,
    ])->assertFailed();
});

// ---- Not found ----

it('fails when bucket not found', function () {
    Prompt::fake();

    MockClient::global([
        ListObjectStorageBucketsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('bucket:update', [
        'bucket' => 'nonexistent',
        '--name' => 'new-name',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();
});

// ---- API error ----

it('throws exception when update API returns 422', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bucketUpdateGetResponse(), 200),
        UpdateObjectStorageBucketRequest::class => MockResponse::make([
            'message' => 'Validation failed',
            'errors' => ['name' => ['Name has already been taken.']],
        ], 422),
    ]);

    $this->artisan('bucket:update', [
        'bucket' => 'fls-bucket-1',
        '--name' => 'taken',
        '--force' => true,
        '--json' => true,
    ]);
})->throws(Saloon\Exceptions\Request\ClientException::class);
