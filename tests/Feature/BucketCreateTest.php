<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\ObjectStorageBuckets\CreateObjectStorageBucketRequest;
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

function bucketCreateResponse(): array
{
    return [
        'data' => [
            'id' => 'fls-bucket-1',
            'type' => 'objectStorageBuckets',
            'attributes' => [
                'name' => 'my-bucket',
                'type' => 'cloudflare_r2',
                'status' => 'creating',
                'visibility' => 'private',
                'jurisdiction' => 'default',
                'endpoint' => null,
                'url' => null,
                'allowed_origins' => null,
                'created_at' => now()->toISOString(),
            ],
            'relationships' => [
                'keys' => ['data' => []],
            ],
        ],
    ];
}

it('creates a bucket with non-interactive options', function () {
    Prompt::fake();

    MockClient::global([
        CreateObjectStorageBucketRequest::class => MockResponse::make(bucketCreateResponse(), 200),
    ]);

    $this->artisan('bucket:create', [
        '--name' => 'my-bucket',
        '--visibility' => 'private',
        '--jurisdiction' => 'default',
        '--key-name' => 'my-key',
        '--key-permission' => 'read_write',
        '--allowed-origins' => '',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('creates a bucket with JSON output', function () {
    MockClient::global([
        CreateObjectStorageBucketRequest::class => MockResponse::make(bucketCreateResponse(), 200),
    ]);

    $this->artisan('bucket:create', [
        '--name' => 'my-bucket',
        '--visibility' => 'private',
        '--jurisdiction' => 'default',
        '--key-name' => 'my-key',
        '--key-permission' => 'read_write',
        '--allowed-origins' => '',
        '--json' => true,
    ])->assertSuccessful();
});

it('creates a bucket with allowed origins', function () {
    Prompt::fake();

    MockClient::global([
        CreateObjectStorageBucketRequest::class => MockResponse::make(bucketCreateResponse(), 200),
    ]);

    $this->artisan('bucket:create', [
        '--name' => 'my-bucket',
        '--visibility' => 'public',
        '--jurisdiction' => 'eu',
        '--key-name' => 'my-key',
        '--key-permission' => 'read_only',
        '--allowed-origins' => 'https://example.com,https://other.com',
        '--no-interaction' => true,
    ])->assertSuccessful();
});
