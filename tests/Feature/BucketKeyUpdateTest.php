<?php

use App\Client\Resources\BucketKeys\GetBucketKeyRequest;
use App\Client\Resources\BucketKeys\ListBucketKeysRequest;
use App\Client\Resources\BucketKeys\UpdateBucketKeyRequest;
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

function bkUpdateBucketListResponse(): array
{
    return [
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
                'relationships' => ['keys' => ['data' => [['id' => 'flsk-key-1', 'type' => 'bucketKeys']]]],
            ],
        ],
        'included' => [],
        'links' => ['next' => null],
    ];
}

function bkUpdateKeyListResponse(): array
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

function bkUpdateKeyDetailResponse(array $overrides = []): array
{
    $base = [
        'data' => [
            'id' => 'flsk-key-1',
            'type' => 'bucketKeys',
            'attributes' => [
                'name' => 'my-key',
                'permission' => 'read_write',
                'created_at' => now()->toISOString(),
            ],
        ],
    ];

    if (isset($overrides['attributes'])) {
        $base['data']['attributes'] = array_merge($base['data']['attributes'], $overrides['attributes']);
    }

    return $base;
}

// ---- Update key name with --force ----

it('updates bucket key name with --force', function () {
    Prompt::fake();

    MockClient::global([
        ListObjectStorageBucketsRequest::class => MockResponse::make(bkUpdateBucketListResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make(bkUpdateKeyListResponse(), 200),
        UpdateBucketKeyRequest::class => MockResponse::make(
            bkUpdateKeyDetailResponse(['attributes' => ['name' => 'renamed-key']]),
            200,
        ),
        GetBucketKeyRequest::class => MockResponse::make(
            bkUpdateKeyDetailResponse(['attributes' => ['name' => 'renamed-key']]),
            200,
        ),
    ]);

    $this->artisan('bucket-key:update', [
        'key' => 'flsk-key-1',
        '--name' => 'renamed-key',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('updates bucket key with --json output', function () {
    MockClient::global([
        ListObjectStorageBucketsRequest::class => MockResponse::make(bkUpdateBucketListResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make(bkUpdateKeyListResponse(), 200),
        UpdateBucketKeyRequest::class => MockResponse::make(
            bkUpdateKeyDetailResponse(['attributes' => ['name' => 'renamed-key']]),
            200,
        ),
        GetBucketKeyRequest::class => MockResponse::make(
            bkUpdateKeyDetailResponse(['attributes' => ['name' => 'renamed-key']]),
            200,
        ),
    ]);

    $this->artisan('bucket-key:update', [
        'key' => 'flsk-key-1',
        '--name' => 'renamed-key',
        '--force' => true,
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"id"');
});

// ---- Resolve key by name ----

it('updates bucket key resolved by name', function () {
    Prompt::fake();

    MockClient::global([
        ListObjectStorageBucketsRequest::class => MockResponse::make(bkUpdateBucketListResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make(bkUpdateKeyListResponse(), 200),
        UpdateBucketKeyRequest::class => MockResponse::make(
            bkUpdateKeyDetailResponse(['attributes' => ['name' => 'renamed-key']]),
            200,
        ),
        GetBucketKeyRequest::class => MockResponse::make(
            bkUpdateKeyDetailResponse(['attributes' => ['name' => 'renamed-key']]),
            200,
        ),
    ]);

    $this->artisan('bucket-key:update', [
        'key' => 'my-key',
        '--name' => 'renamed-key',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

// ---- No fields to update ----

it('fails when no fields provided in non-interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        ListObjectStorageBucketsRequest::class => MockResponse::make(bkUpdateBucketListResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make(bkUpdateKeyListResponse(), 200),
    ]);

    $this->artisan('bucket-key:update', [
        'key' => 'flsk-key-1',
        '--no-interaction' => true,
    ])->assertFailed();
});

// ---- Key not found ----

it('fails when key not found', function () {
    Prompt::fake();

    MockClient::global([
        ListObjectStorageBucketsRequest::class => MockResponse::make(bkUpdateBucketListResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('bucket-key:update', [
        'key' => 'flsk-nonexistent',
        '--name' => 'new-name',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();
});

// ---- API error ----

it('throws exception when update API returns 422', function () {
    Prompt::fake();

    MockClient::global([
        ListObjectStorageBucketsRequest::class => MockResponse::make(bkUpdateBucketListResponse(), 200),
        ListBucketKeysRequest::class => MockResponse::make(bkUpdateKeyListResponse(), 200),
        UpdateBucketKeyRequest::class => MockResponse::make([
            'message' => 'Validation failed',
            'errors' => ['name' => ['Name has already been taken.']],
        ], 422),
    ]);

    $this->artisan('bucket-key:update', [
        'key' => 'flsk-key-1',
        '--name' => 'taken',
        '--force' => true,
        '--json' => true,
    ]);
})->throws(Saloon\Exceptions\Request\ClientException::class);
