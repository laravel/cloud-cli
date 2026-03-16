<?php

use App\Client\Resources\Meta\GetOrganizationRequest;
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

function bucketListOrgResponse(): array
{
    return [
        'data' => [
            'id' => 'org-1',
            'type' => 'organizations',
            'attributes' => ['name' => 'My Org', 'slug' => 'my-org'],
        ],
    ];
}

function bucketListItemResponse(array $overrides = []): array
{
    return array_merge([
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
    ], $overrides);
}

it('lists buckets successfully', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(bucketListOrgResponse(), 200),
        ListObjectStorageBucketsRequest::class => MockResponse::make([
            'data' => [bucketListItemResponse()],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('bucket:list')
        ->assertSuccessful();
});

it('outputs empty JSON when no buckets found with --json', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(bucketListOrgResponse(), 200),
        ListObjectStorageBucketsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    // In non-interactive mode, outputJsonIfWanted exits with SUCCESS before reaching warning
    $this->artisan('bucket:list', ['--json' => true])
        ->assertSuccessful();
});

it('lists buckets with JSON output', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(bucketListOrgResponse(), 200),
        ListObjectStorageBucketsRequest::class => MockResponse::make([
            'data' => [bucketListItemResponse()],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('bucket:list', ['--json' => true])
        ->assertSuccessful();
});

it('lists multiple buckets', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(bucketListOrgResponse(), 200),
        ListObjectStorageBucketsRequest::class => MockResponse::make([
            'data' => [
                bucketListItemResponse(),
                bucketListItemResponse([
                    'id' => 'fls-bucket-2',
                    'attributes' => [
                        'name' => 'second-bucket',
                        'type' => 'cloudflare_r2',
                        'status' => 'available',
                        'visibility' => 'public',
                        'jurisdiction' => 'eu',
                        'endpoint' => 'https://example.com',
                        'url' => 'https://example.com/second-bucket',
                        'allowed_origins' => null,
                        'created_at' => now()->toISOString(),
                    ],
                    'relationships' => ['keys' => ['data' => []]],
                ]),
            ],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('bucket:list')
        ->assertSuccessful();
});
