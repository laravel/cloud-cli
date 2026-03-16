<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
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

function getApplicationFullResponse(array $overrides = []): array
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

// ---- Get by ID ----

it('gets application by ID successfully in non-interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        GetApplicationRequest::class => MockResponse::make(getApplicationFullResponse(), 200),
    ]);

    $this->artisan('application:get', [
        'application' => 'app-123',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('gets application by ID with --json and outputs JSON', function () {
    MockClient::global([
        GetApplicationRequest::class => MockResponse::make(getApplicationFullResponse(), 200),
    ]);

    $this->artisan('application:get', [
        'application' => 'app-123',
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"id"');
});

// ---- Get by name ----

it('gets application by name in non-interactive mode', function () {
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
    ]);

    $this->artisan('application:get', [
        'application' => 'My App',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('gets application by name with --json', function () {
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
    ]);

    $this->artisan('application:get', [
        'application' => 'My App',
        '--json' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"id"');
});

// ---- Not found ----

it('returns failure when application not found by ID', function () {
    Prompt::fake();

    MockClient::global([
        GetApplicationRequest::class => MockResponse::make(['message' => 'Not found'], 404),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('application:get', [
        'application' => 'app-nonexistent',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('returns failure when application not found by name', function () {
    Prompt::fake();

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('application:get', [
        'application' => 'nonexistent-app',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('auto-selects when only one application exists and no argument given', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('');

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
    ]);

    $this->artisan('application:get', ['--no-interaction' => true])
        ->assertSuccessful();
});

it('fails when no argument given and no applications exist', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('remoteRepo')->andReturn('');

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('application:get', ['--no-interaction' => true])
        ->assertFailed();
});
