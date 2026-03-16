<?php

use App\Client\Resources\Applications\DeleteApplicationRequest;
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

function deleteApplicationFullResponse(array $overrides = []): array
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

// ---- Delete with --force by ID ----

it('deletes application by ID with --force', function () {
    Prompt::fake();

    MockClient::global([
        GetApplicationRequest::class => MockResponse::make(deleteApplicationFullResponse(), 200),
        DeleteApplicationRequest::class => MockResponse::make([], 204),
    ]);

    $this->artisan('application:delete', [
        'application' => 'app-123',
        '--force' => true,
    ])->assertSuccessful();
});

// ---- Delete with --force by name ----

it('deletes application by name with --force in non-interactive mode', function () {
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
        DeleteApplicationRequest::class => MockResponse::make([], 204),
    ]);

    $this->artisan('application:delete', [
        'application' => 'My App',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

// ---- Delete not found ----

it('returns failure when application not found by name for deletion', function () {
    Prompt::fake();

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('application:delete', [
        'application' => 'nonexistent-app',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();
});

it('returns failure when application not found by ID for deletion', function () {
    Prompt::fake();

    MockClient::global([
        GetApplicationRequest::class => MockResponse::make(['message' => 'Not found'], 404),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('application:delete', [
        'application' => 'app-nonexistent',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();
});

// ---- Delete with auto-resolve (single app, no argument) ----

it('deletes the only application when no argument given and --force', function () {
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
        DeleteApplicationRequest::class => MockResponse::make([], 204),
    ]);

    $this->artisan('application:delete', [
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

// ---- Bug: ApplicationDelete catches wrong exception type ----

it('notes that ApplicationDelete catches Illuminate RequestException instead of Saloon RequestException', function () {
    // BUG: ApplicationDelete.php imports and catches Illuminate\Http\Client\RequestException
    // but the Saloon HTTP client throws Saloon\Exceptions\Request\RequestException.
    // This means API errors during deletion (e.g., 500) will NOT be caught by the
    // try/catch block and will instead propagate as uncaught exceptions.
    // See BUGS_FOUND.md for details.
})->skip('Documents a bug - see BUGS_FOUND.md');
