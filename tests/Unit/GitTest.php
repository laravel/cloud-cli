<?php

use App\Git;
use Illuminate\Support\Facades\Process;

it('parses remote URLs', function (string $remoteUrl, string $expected) {
    Process::fake([
        '*git*remote*get-url*origin*' => Process::result($remoteUrl),
    ]);

    expect(app(Git::class)->remoteRepo())->toBe($expected);
})->with([
    'SSH with .git' => ['git@github.com:user/repo.git', 'user/repo'],
    'SSH without .git' => ['git@github.com:user/repo', 'user/repo'],
    'HTTPS with .git' => ['https://github.com/user/repo.git', 'user/repo'],
    'HTTPS without .git' => ['https://github.com/user/repo', 'user/repo'],
    'GitLab SSH' => ['git@gitlab.com:user/repo.git', 'user/repo'],
    'GitLab HTTPS' => ['https://gitlab.com/user/repo.git', 'user/repo'],
    'Bitbucket SSH' => ['git@bitbucket.org:user/repo.git', 'user/repo'],
    'Bitbucket HTTPS' => ['https://bitbucket.org/user/repo.git', 'user/repo'],
]);
