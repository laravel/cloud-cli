<?php

use App\Enums\SourceProvider;
use App\Exceptions\UnsupportedSourceProviderException;
use App\Git;
use App\SourceProviders\BitbucketProvider;
use App\SourceProviders\GitHubProvider;
use App\SourceProviders\GitLabProvider;
use App\SourceProviders\GitLabSelfHostedProvider;
use App\SourceProviders\SourceProviderManager;

it('detects a provider from a remote host', function (?string $host, ?SourceProvider $expected) {
    expect(SourceProvider::fromHost($host))->toBe($expected);
})->with([
    'GitHub' => ['github.com', SourceProvider::GITHUB],
    'GitLab' => ['gitlab.com', SourceProvider::GITLAB],
    'Bitbucket' => ['bitbucket.org', SourceProvider::BITBUCKET],
    'self-hosted' => ['git.example.com', null],
    'no remote' => [null, null],
]);

it('offers every provider the API accepts', function () {
    expect(SourceProvider::options())->toHaveKeys(['github', 'gitlab', 'gitlab_self_hosted', 'bitbucket']);
});

it('builds commit and branch URLs per provider', function () {
    $github = new GitHubProvider;
    $gitlab = new GitLabProvider;
    $selfHosted = new GitLabSelfHostedProvider('git.example.com');
    $bitbucket = new BitbucketProvider;

    expect($github->commitUrl('user/repo', 'abc123'))->toBe('https://github.com/user/repo/commit/abc123')
        ->and($github->branchUrl('user/repo', 'main'))->toBe('https://github.com/user/repo/tree/main')
        ->and($gitlab->commitUrl('group/repo', 'abc123'))->toBe('https://gitlab.com/group/repo/-/commit/abc123')
        ->and($gitlab->branchUrl('group/repo', 'main'))->toBe('https://gitlab.com/group/repo/-/tree/main')
        ->and($selfHosted->commitUrl('group/sub/repo', 'abc123'))->toBe('https://git.example.com/group/sub/repo/-/commit/abc123')
        ->and($selfHosted->repositoryUrl('group/sub/repo'))->toBe('https://git.example.com/group/sub/repo')
        ->and($bitbucket->commitUrl('workspace/repo', 'abc123'))->toBe('https://bitbucket.org/workspace/repo/commits/abc123')
        ->and($bitbucket->branchUrl('workspace/repo', 'main'))->toBe('https://bitbucket.org/workspace/repo/branch/main');
});

it('resolves a driver for every provider', function (SourceProvider $provider) {
    $manager = new SourceProviderManager(app(Git::class));

    expect($manager->driver($provider, 'git.example.com')->provider())->toBe($provider);
})->with(SourceProvider::cases());

it('refuses to build a self-hosted driver with no instance to point at', function () {
    $git = Mockery::mock(Git::class);
    $git->shouldReceive('remoteHost')->andReturn(null);

    (new SourceProviderManager($git))->driver(SourceProvider::GITLAB_SELF_HOSTED);
})->throws(UnsupportedSourceProviderException::class);

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
