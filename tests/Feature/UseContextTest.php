<?php

use App\ConfigRepository;
use App\Git;
use App\LocalConfig;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Sleep::fake();

    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    $this->mockLocalConfig = Mockery::mock(LocalConfig::class);
    $this->app->instance(LocalConfig::class, $this->mockLocalConfig);
});

it('displays current context when called without arguments', function () {
    $this->mockLocalConfig->shouldReceive('applicationId')->andReturn('app-123');
    $this->mockLocalConfig->shouldReceive('environmentId')->andReturn('env-456');

    $this->artisan('use')
        ->assertSuccessful();
});

it('shows warning when no context is set', function () {
    $this->mockLocalConfig->shouldReceive('applicationId')->andReturn(null);
    $this->mockLocalConfig->shouldReceive('environmentId')->andReturn(null);

    $this->artisan('use')
        ->assertSuccessful();
});

it('clears context with --clear option', function () {
    $this->mockLocalConfig->shouldReceive('remove')
        ->once()
        ->with('application_id', 'environment_id');

    $this->artisan('use', ['--clear' => true])
        ->assertSuccessful();
});
