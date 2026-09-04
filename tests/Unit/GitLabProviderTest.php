<?php

use App\SourceProviders\GitLabProvider;
use App\SourceProviders\GitLabSelfHostedProvider;
use Illuminate\Support\Facades\Process;

it('reads owners from glab without piping through jq', function () {
    Process::fake([
        '*glab*api*user*' => Process::result(json_encode(['username' => 'jane'])),
        '*glab*api*groups*' => Process::result(json_encode([
            ['full_path' => 'acme'],
            ['full_path' => 'acme/platform'],
        ])),
    ]);

    expect((new GitLabProvider)->owners()->all())->toBe([
        'jane' => 'jane',
        'acme' => 'acme',
        'acme/platform' => 'acme/platform',
    ]);
});

it('returns no owners when glab fails', function () {
    Process::fake(fn () => Process::result('', 'not logged in', 1));

    expect((new GitLabProvider)->owners())->toBeEmpty();
});

it('always states visibility, since GitLab projects default to internal', function () {
    Process::fake();

    (new GitLabProvider)->createRepository('my-app', 'acme', private: true);

    Process::assertRan(fn ($process) => $process->command === [
        'glab', 'repo', 'create', 'acme/my-app', '--private', '--remoteName', 'origin',
    ]);
});

it('points glab at the instance a self-hosted repository lives on', function () {
    Process::fake();

    $provider = new GitLabSelfHostedProvider('git.example.com');
    $provider->cliAuthenticated();
    $provider->createRepository('my-app', 'acme', private: false);

    Process::assertRan(fn ($process) => $process->command === [
        'glab', 'auth', 'status', '--hostname', 'git.example.com',
    ]);

    // `glab repo create` takes no --hostname, so the host has to lead the project path.
    Process::assertRan(fn ($process) => $process->command === [
        'glab', 'repo', 'create', 'git.example.com/acme/my-app', '--public', '--remoteName', 'origin',
    ]);
});
