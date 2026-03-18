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
    $this->mockGit->shouldReceive('currentBranch')->andReturn('main')->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('')->byDefault();
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(false)->byDefault();
    $this->app->instance(Git::class, $this->mockGit);
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(fn () => MockClient::destroyGlobal());

it('lists tokens with --list option', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
    ]);

    $this->artisan('auth:token', ['--list' => true])
        ->assertSuccessful();
});

it('reveals config file path with --reveal option', function () {
    Prompt::fake();

    $this->mockConfig->shouldReceive('path')->andReturn('/tmp/.cloud-cli/config.json');

    $this->artisan('auth:token', ['--reveal' => true])
        ->assertSuccessful();
});

// Note: The --add and --remove options require interactive prompt input (password/select)
// which cannot be reliably faked with Prompt::fake() since it takes raw key presses,
// not label=>value mappings. The --list and --reveal options above provide adequate
// coverage for the non-interactive code paths.
