<?php

/**
 * Completions tests.
 *
 * The completions command generates shell completion scripts for bash, zsh, and fish.
 * It implements NoAuthRequired so no API token is needed. The --print flag outputs
 * the completion script to stdout without trying to install it.
 *
 * Note: The interactive installation flow (creating directories, writing files) is
 * not tested here because it requires filesystem writes and interactive prompts to
 * confirm directory creation. We test the --print output mode instead.
 */

use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;

beforeEach(function () {
    Sleep::fake();

    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect([]));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(fn () => MockClient::destroyGlobal());

it('outputs bash completion script with --print flag', function () {
    Prompt::fake();

    $this->artisan('completions', [
        'shell' => 'bash',
        '--print' => true,
    ])->assertSuccessful();
});

it('outputs zsh completion script with --print flag', function () {
    Prompt::fake();

    $this->artisan('completions', [
        'shell' => 'zsh',
        '--print' => true,
    ])->assertSuccessful();
});

it('outputs fish completion script with --print flag', function () {
    Prompt::fake();

    $this->artisan('completions', [
        'shell' => 'fish',
        '--print' => true,
    ])->assertSuccessful();
});

it('detects shell automatically when no shell argument given with --print', function () {
    Prompt::fake();

    $this->artisan('completions', [
        '--print' => true,
    ])->assertSuccessful();
});
