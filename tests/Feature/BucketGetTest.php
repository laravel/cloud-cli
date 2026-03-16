<?php

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

function bucketGetResponse(array $overrides = []): array
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

// ---- Get by ID ----

it('gets bucket by ID successfully', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bucketGetResponse(), 200),
    ]);

    $this->artisan('bucket:get', [
        'bucket' => 'fls-bucket-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('gets bucket by ID with --json output', function () {
    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(bucketGetResponse(), 200),
    ]);

    $this->artisan('bucket:get', [
        'bucket' => 'fls-bucket-1',
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"id"');
});

// ---- Get by name ----

it('gets bucket by name', function () {
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
    ]);

    $this->artisan('bucket:get', [
        'bucket' => 'my-bucket',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

// ---- Not found ----

it('fails when bucket not found by ID', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make(['message' => 'Not found'], 404),
        ListObjectStorageBucketsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('bucket:get', [
        'bucket' => 'fls-nonexistent',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('fails when no buckets exist and no argument given', function () {
    Prompt::fake();

    MockClient::global([
        ListObjectStorageBucketsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('bucket:get', [
        '--no-interaction' => true,
    ])->assertFailed();
});

// ---- Auto-select when only one bucket ----

it('auto-selects when only one bucket exists and no argument given', function () {
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
    ]);

    $this->artisan('bucket:get', ['--no-interaction' => true])
        ->assertSuccessful();
});

// ---- Public bucket ----

it('gets public bucket with EU jurisdiction', function () {
    Prompt::fake();

    MockClient::global([
        GetObjectStorageBucketRequest::class => MockResponse::make([
            'data' => [
                'id' => 'fls-bucket-2',
                'type' => 'objectStorageBuckets',
                'attributes' => [
                    'name' => 'public-bucket',
                    'type' => 'cloudflare_r2',
                    'status' => 'available',
                    'visibility' => 'public',
                    'jurisdiction' => 'eu',
                    'endpoint' => 'https://eu.example.com',
                    'url' => 'https://eu.example.com/public-bucket',
                    'allowed_origins' => ['https://myapp.com'],
                    'created_at' => now()->toISOString(),
                ],
                'relationships' => ['keys' => ['data' => []]],
            ],
        ], 200),
    ]);

    $this->artisan('bucket:get', [
        'bucket' => 'fls-bucket-2',
        '--no-interaction' => true,
    ])->assertSuccessful();
});
