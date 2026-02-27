<?php

namespace App;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class Git
{
    public function isRepo(): bool
    {
        return $this->run(['git', 'rev-parse', '--is-inside-work-tree'])->successful();
    }

    public function getRoot(): ?string
    {
        $result = $this->run(['git', 'rev-parse', '--show-toplevel']);

        if (! $result->successful()) {
            return null;
        }

        return trim($result->output());
    }

    public function hasGitHubRemote(): bool
    {
        $result = $this->run(['git', 'remote', '-v']);

        if (! $result->successful()) {
            return false;
        }

        return Str::contains($result->output(), 'github.com');
    }

    public function initRepo(): bool
    {
        return $this->run(['git', 'init'])->successful();
    }

    public function addRemote(string $name, string $url): bool
    {
        return $this->run(['git', 'remote', 'add', $name, $url])->successful();
    }

    public function ghInstalled(): bool
    {
        return $this->run(['which', 'gh'])->successful();
    }

    public function ghAuthenticated(): bool
    {
        return $this->run(['gh', 'auth', 'status'])->successful();
    }

    public function getGitHubOrgs(): Collection
    {
        $result = $this->run(['gh', 'api', 'user/orgs', '--jq', '.[].login']);

        if (! $result->successful()) {
            return collect();
        }

        return collect(array_filter(explode("\n", trim($result->output()))));
    }

    public function getGitHubUsername(): ?string
    {
        $result = $this->run(['gh', 'api', 'user', '--jq', '.login']);

        if (! $result->successful()) {
            return null;
        }

        return trim($result->output());
    }

    public function createGitHubRepo(string $name, string $org, bool $private): ProcessResult
    {
        $visibility = $private ? '--private' : '--public';

        $repoName = $org.'/'.$name;

        return $this->run(['gh', 'repo', 'create', $repoName, $visibility, '--source', '.', '--remote', 'origin']);
    }

    public function currentDirectoryName(): string
    {
        return basename(getcwd());
    }

    public function remoteRepo(): string
    {
        $url = str($this->run(['git', 'remote', 'get-url', 'origin'])->output())->trim();

        $repo = $url->contains('://')
            ? $url->after('://')->after('/')
            : $url->after(':');

        return $repo->beforeLast('.git')->toString();
    }

    public function addAll(): bool
    {
        return $this->run(['git', 'add', '-A'])->successful();
    }

    public function commit(string $message): ProcessResult
    {
        return $this->run(['git', 'commit', '-m', $message]);
    }

    public function push(): ProcessResult
    {
        return $this->run(['git', 'push', '-u', 'origin', 'HEAD']);
    }

    public function getDefaultBranch(): string
    {
        $result = $this->run(['git', 'symbolic-ref', 'refs/remotes/origin/HEAD']);

        if ($result->successful()) {
            return str($result->output())->trim()->afterLast('/')->toString();
        }

        $result = $this->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);

        if ($result->successful()) {
            return trim($result->output());
        }

        return 'main';
    }

    public function currentBranch(): string
    {
        $result = $this->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);

        if ($result->successful()) {
            return trim($result->output());
        }

        return $this->getDefaultBranch();
    }

    public static function commitUrl(string $repositoryFullName, string $commitHash): string
    {
        return sprintf('https://github.com/%s/commit/%s', $repositoryFullName, $commitHash);
    }

    public static function branchUrl(string $repositoryFullName, string $branchName): string
    {
        return sprintf('https://github.com/%s/tree/%s', $repositoryFullName, $branchName);
    }

    protected function run(array $command): ProcessResult
    {
        return Process::run($command);
    }
}
