<?php

use App\Git;
use Illuminate\Support\Facades\Process;

it('parses SSH remote URLs', function () {
    Process::fake([
        '*git*remote*get-url*origin*' => Process::result('git@github.com:user/repo.git'),
    ]);

    expect(app(Git::class)->remoteRepo())->toBe('user/repo');
});

it('parses HTTPS remote URLs', function () {
    Process::fake([
        '*git*remote*get-url*origin*' => Process::result('https://github.com/user/repo.git'),
    ]);

    expect(app(Git::class)->remoteRepo())->toBe('user/repo');
});

it('parses HTTPS remote URLs without .git suffix', function () {
    Process::fake([
        '*git*remote*get-url*origin*' => Process::result('https://github.com/user/repo'),
    ]);

    expect(app(Git::class)->remoteRepo())->toBe('user/repo');
});

it('parses SSH remote URLs without .git suffix', function () {
    Process::fake([
        '*git*remote*get-url*origin*' => Process::result('git@github.com:user/repo'),
    ]);

    expect(app(Git::class)->remoteRepo())->toBe('user/repo');
});
