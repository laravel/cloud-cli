<?php

use App\Commands\ApplicationList;
use App\Commands\EnvironmentList;
use App\Commands\EnvironmentLogs;
use App\Commands\EnvironmentVariables;
use App\Commands\Status;
use App\ConfigRepository;
use App\Git;
use Illuminate\Contracts\Console\Kernel;
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
});

it('registers the logs alias for environment:logs', function () {
    $command = $this->app->make(Kernel::class)->all();

    expect($command)->toHaveKey('logs');
    expect($command['logs'])->toBeInstanceOf(EnvironmentLogs::class);
});

it('registers the vars alias for environment:variables', function () {
    $command = $this->app->make(Kernel::class)->all();

    expect($command)->toHaveKey('vars');
    expect($command['vars'])->toBeInstanceOf(EnvironmentVariables::class);
});

it('registers the envs alias for environment:list', function () {
    $command = $this->app->make(Kernel::class)->all();

    expect($command)->toHaveKey('envs');
    expect($command['envs'])->toBeInstanceOf(EnvironmentList::class);
});

it('registers the apps alias for application:list', function () {
    $command = $this->app->make(Kernel::class)->all();

    expect($command)->toHaveKey('apps');
    expect($command['apps'])->toBeInstanceOf(ApplicationList::class);
});

it('registers the status command', function () {
    $command = $this->app->make(Kernel::class)->all();

    expect($command)->toHaveKey('status');
    expect($command['status'])->toBeInstanceOf(Status::class);
});
