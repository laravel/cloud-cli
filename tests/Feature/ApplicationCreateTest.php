<?php

use App\Client\Resources\Applications\CreateApplicationRequest;
use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
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
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function fullApplicationResponse(): array
{
    return [
        'data' => createApplicationResponse(),
        'included' => [
            ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
            ['id' => 'env-1', 'type' => 'environments', 'attributes' => [
                'name' => 'production',
                'slug' => 'production',
                'vanity_domain' => 'my-app.cloud.laravel.com',
                'status' => 'running',
                'php_major_version' => '8.3',
            ]],
        ],
    ];
}

function setupCreateMocks(): void
{
    MockClient::global([
        ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [createApplicationResponse()],
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
            ],
            'links' => ['next' => null],
        ], 200),
        CreateApplicationRequest::class => MockResponse::make(fullApplicationResponse(), 200),
        GetApplicationRequest::class => MockResponse::make(fullApplicationResponse(), 200),
    ]);
}

it('creates application successfully with all options in non-interactive mode', function () {
    Prompt::fake();

    setupCreateMocks();

    $this->artisan('application:create', [
        '--name' => 'My App',
        '--repository' => 'user/my-app',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('creates application with --json and outputs JSON', function () {
    setupCreateMocks();

    $this->artisan('application:create', [
        '--name' => 'My App',
        '--repository' => 'user/my-app',
        '--region' => 'us-east-1',
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"id"');
});

it('handles validation error 422 on create in non-interactive mode', function () {
    MockClient::global([
        ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [createApplicationResponse()],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        CreateApplicationRequest::class => MockResponse::make([
            'message' => 'Validation failed',
            'errors' => ['name' => ['Name has already been taken.']],
        ], 422),
    ]);

    $this->artisan('application:create', [
        '--name' => 'Taken',
        '--repository' => 'user/my-app',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('handles validation error 422 on create with --json', function () {
    MockClient::global([
        ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [createApplicationResponse()],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        CreateApplicationRequest::class => MockResponse::make([
            'message' => 'Validation failed',
            'errors' => ['name' => ['Name has already been taken.']],
        ], 422),
    ]);

    $this->artisan('application:create', [
        '--name' => 'Taken',
        '--repository' => 'user/my-app',
        '--region' => 'us-east-1',
        '--json' => true,
    ])->assertFailed();
});

it('handles server error 500 on create in non-interactive mode', function () {
    MockClient::global([
        ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [createApplicationResponse()],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        CreateApplicationRequest::class => MockResponse::make(['message' => 'Server error'], 500),
    ]);

    $this->artisan('application:create', [
        '--name' => 'My App',
        '--repository' => 'user/my-app',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('uses git remote repo as default repository in non-interactive mode', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/auto-detected');

    setupCreateMocks();

    $this->artisan('application:create', [
        '--name' => 'My App',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('falls back to default region when no region option is provided', function () {
    Prompt::fake();

    setupCreateMocks();

    $this->artisan('application:create', [
        '--name' => 'My App',
        '--repository' => 'user/my-app',
        '--no-interaction' => true,
    ])->assertSuccessful();
});
