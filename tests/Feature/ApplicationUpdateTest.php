<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Applications\UpdateApplicationRequest;
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
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function updateApplicationFullResponse(array $overrides = []): array
{
    return [
        'data' => createApplicationResponse($overrides),
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

function setupUpdateMocks(array $updatedOverrides = []): void
{
    MockClient::global([
        GetApplicationRequest::class => MockResponse::make(updateApplicationFullResponse(), 200),
        UpdateApplicationRequest::class => MockResponse::make(
            updateApplicationFullResponse($updatedOverrides),
            200,
        ),
    ]);
}

// ---- Update with --force in non-interactive mode ----

it('updates application by ID with --force and --name in non-interactive mode', function () {
    Prompt::fake();

    setupUpdateMocks(['attributes' => ['name' => 'New Name', 'slug' => 'my-app', 'region' => 'us-east-1']]);

    $this->artisan('application:update', [
        'application' => 'app-123',
        '--name' => 'New Name',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('updates application with --force and --json outputs JSON', function () {
    setupUpdateMocks(['attributes' => ['name' => 'New Name', 'slug' => 'my-app', 'region' => 'us-east-1']]);

    $this->artisan('application:update', [
        'application' => 'app-123',
        '--name' => 'New Name',
        '--force' => true,
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"id"');
});

it('updates application slug and repository with --force', function () {
    Prompt::fake();

    setupUpdateMocks(['attributes' => ['name' => 'My App', 'slug' => 'new-slug', 'region' => 'us-east-1']]);

    $this->artisan('application:update', [
        'application' => 'app-123',
        '--slug' => 'new-slug',
        '--repository' => 'user/other-repo',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

// ---- Update by name ----

it('updates application by name in non-interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [createApplicationResponse()],
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
            'links' => ['next' => null],
        ], 200),
        GetApplicationRequest::class => MockResponse::make(updateApplicationFullResponse(), 200),
        UpdateApplicationRequest::class => MockResponse::make(
            updateApplicationFullResponse(['attributes' => ['name' => 'New Name', 'slug' => 'my-app', 'region' => 'us-east-1']]),
            200,
        ),
    ]);

    $this->artisan('application:update', [
        'application' => 'My App',
        '--name' => 'New Name',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

// ---- No fields to update ----

it('returns failure when no fields provided in non-interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        GetApplicationRequest::class => MockResponse::make(updateApplicationFullResponse(), 200),
    ]);

    $this->artisan('application:update', [
        'application' => 'app-123',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('returns failure with JSON error when no fields to update', function () {
    MockClient::global([
        GetApplicationRequest::class => MockResponse::make(updateApplicationFullResponse(), 200),
    ]);

    $this->artisan('application:update', [
        'application' => 'app-123',
        '--json' => true,
    ])->assertFailed();
});

// ---- Validation error on update ----

// BUG: ApplicationUpdate does not wrap the update API call in loopUntilValid or try/catch.
// Unlike ApplicationCreate (which uses loopUntilValid), the update command's updateApplication()
// method lets Saloon exceptions propagate uncaught. A 422 or 500 from the update API results
// in an unhandled exception rather than a graceful error message.
// See BUGS_FOUND.md for details.

it('throws unhandled exception when update API returns 422', function () {
    Prompt::fake();

    MockClient::global([
        GetApplicationRequest::class => MockResponse::make(updateApplicationFullResponse(), 200),
        UpdateApplicationRequest::class => MockResponse::make([
            'message' => 'Validation failed',
            'errors' => ['name' => ['Name has already been taken.']],
        ], 422),
    ]);

    $this->artisan('application:update', [
        'application' => 'app-123',
        '--name' => 'Taken',
        '--force' => true,
        '--json' => true,
    ]);
})->throws(Saloon\Exceptions\Request\ClientException::class);

// ---- Server error on update ----

it('throws unhandled exception when update API returns 500', function () {
    Prompt::fake();

    MockClient::global([
        GetApplicationRequest::class => MockResponse::make(updateApplicationFullResponse(), 200),
        UpdateApplicationRequest::class => MockResponse::make(['message' => 'Server error'], 500),
    ]);

    $this->artisan('application:update', [
        'application' => 'app-123',
        '--name' => 'New Name',
        '--force' => true,
        '--json' => true,
    ]);
})->throws(Saloon\Exceptions\Request\ServerException::class);

// ---- Application not found ----

it('returns failure when application not found on update', function () {
    Prompt::fake();

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('application:update', [
        'application' => 'nonexistent',
        '--name' => 'New Name',
        '--no-interaction' => true,
    ])->assertFailed();
});

// ---- Update with --slack-channel ----

it('updates application slack channel with --force', function () {
    Prompt::fake();

    setupUpdateMocks();

    $this->artisan('application:update', [
        'application' => 'app-123',
        '--slack-channel' => '#deploys',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

// ---- Multiple fields at once ----

it('updates multiple fields at once with --force', function () {
    Prompt::fake();

    setupUpdateMocks(['attributes' => ['name' => 'New Name', 'slug' => 'new-slug', 'region' => 'us-east-1']]);

    $this->artisan('application:update', [
        'application' => 'app-123',
        '--name' => 'New Name',
        '--slug' => 'new-slug',
        '--repository' => 'user/other-repo',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});
