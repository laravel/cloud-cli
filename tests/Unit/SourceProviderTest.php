<?php

use App\Enums\SourceProvider;
use App\Exceptions\UnsupportedSourceProviderException;
use App\Git;
use App\SourceProviders\GitHubProvider;
use App\SourceProviders\GitLabProvider;
use App\SourceProviders\GitLabSelfHostedProvider;
use App\SourceProviders\SourceProviderManager;

it('detects a provider from a remote host', function (?string $host, ?SourceProvider $expected) {
    expect(SourceProvider::fromHost($host))->toBe($expected);
})->with([
    'GitHub' => ['github.com', SourceProvider::GITHUB],
    'GitLab' => ['gitlab.com', SourceProvider::GITLAB],
    'self-hosted' => ['git.example.com', null],
    'Bitbucket has no driver yet' => ['bitbucket.org', null],
    'no remote' => [null, null],
]);

it('offers only providers it has a driver for', function () {
    expect(SourceProvider::options())->not->toHaveKey('bitbucket')
        ->and(SourceProvider::options())->toHaveKeys(['github', 'gitlab', 'gitlab_self_hosted']);
});

it('builds commit and branch URLs per provider', function () {
    $github = new GitHubProvider;
    $gitlab = new GitLabProvider;
    $selfHosted = new GitLabSelfHostedProvider('git.example.com');

    expect($github->commitUrl('user/repo', 'abc123'))->toBe('https://github.com/user/repo/commit/abc123')
        ->and($github->branchUrl('user/repo', 'main'))->toBe('https://github.com/user/repo/tree/main')
        ->and($gitlab->commitUrl('group/repo', 'abc123'))->toBe('https://gitlab.com/group/repo/-/commit/abc123')
        ->and($gitlab->branchUrl('group/repo', 'main'))->toBe('https://gitlab.com/group/repo/-/tree/main')
        ->and($selfHosted->commitUrl('group/sub/repo', 'abc123'))->toBe('https://git.example.com/group/sub/repo/-/commit/abc123')
        ->and($selfHosted->repositoryUrl('group/sub/repo'))->toBe('https://git.example.com/group/sub/repo');
});

it('resolves a driver for every supported provider', function (SourceProvider $provider) {
    $manager = new SourceProviderManager(app(Git::class));

    expect($manager->driver($provider, 'git.example.com')->provider())->toBe($provider);
})->with(fn () => collect(SourceProvider::cases())->filter->supported()->all());

it('refuses to build a driver for a provider it does not support', function () {
    (new SourceProviderManager(app(Git::class)))->driver(SourceProvider::BITBUCKET);
})->throws(UnsupportedSourceProviderException::class, 'Bitbucket is not supported by the CLI yet.');

it('falls back to GitHub when the application is not the repository we are sitting in', function () {
    $git = Mockery::mock(Git::class);
    $git->shouldReceive('remoteRepo')->andReturn('group/some-other-repo');
    $git->shouldReceive('remoteHost')->andReturn('gitlab.com');

    $driver = (new SourceProviderManager($git))->forRepository('user/an-app');

    expect($driver->provider())->toBe(SourceProvider::GITHUB);
});

it('uses the local remote when the application is the repository we are sitting in', function () {
    $git = Mockery::mock(Git::class);
    $git->shouldReceive('remoteRepo')->andReturn('group/my-app');
    $git->shouldReceive('remoteHost')->andReturn('gitlab.com');

    $driver = (new SourceProviderManager($git))->forRepository('group/my-app');

    expect($driver->provider())->toBe(SourceProvider::GITLAB);
});
