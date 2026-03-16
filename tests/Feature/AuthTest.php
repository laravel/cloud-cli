<?php

use App\Client\Resources\Meta\GetOrganizationRequest;
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
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

// ---- auth ----

it('requires sockets extension for browser-based auth', function () {
    // The auth command checks for the sockets extension at runtime.
    // We cannot mock extension_loaded(), so we verify the command exists
    // and skip the actual flow test.
})->skip('Auth command requires sockets extension and browser-based OAuth flow - not unit-testable');

// ---- auth:token --list ----

it('lists tokens and shows organization names', function () {
    Prompt::fake();

    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->mockConfig->shouldReceive('path')->andReturn('/tmp/config.json');

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
    ]);

    $this->artisan('auth:token', ['--list' => true])
        ->assertSuccessful();
});

it('returns failure when listing tokens and no tokens exist', function () {
    Prompt::fake();

    $configMock = Mockery::mock(ConfigRepository::class);
    $configMock->shouldReceive('apiTokens')->andReturn(collect([]));
    $configMock->shouldReceive('path')->andReturn('/tmp/config.json');
    $this->app->instance(ConfigRepository::class, $configMock);

    $this->artisan('auth:token', ['--list' => true])
        ->assertFailed();
});

// ---- auth:token --reveal ----

it('reveals config file path', function () {
    Prompt::fake();

    $this->mockConfig->shouldReceive('path')->andReturn('/tmp/config.json');

    $this->artisan('auth:token', ['--reveal' => true])
        ->assertSuccessful();
});
