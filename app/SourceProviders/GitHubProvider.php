<?php

namespace App\SourceProviders;

use App\Enums\SourceProvider;
use App\SourceProviders\Concerns\RunsCli;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Collection;

class GitHubProvider implements CreatesRepositories, SourceProviderDriver
{
    use RunsCli;

    public function provider(): SourceProvider
    {
        return SourceProvider::GITHUB;
    }

    public function baseUrl(): string
    {
        return 'https://github.com';
    }

    public function repositoryUrl(string $repository): string
    {
        return $this->baseUrl().'/'.$repository;
    }

    public function commitUrl(string $repository, string $commitHash): string
    {
        return $this->repositoryUrl($repository).'/commit/'.$commitHash;
    }

    public function branchUrl(string $repository, string $branchName): string
    {
        return $this->repositoryUrl($repository).'/tree/'.$branchName;
    }

    public function cliName(): string
    {
        return 'gh';
    }

    public function cliInstalled(): bool
    {
        return $this->run(['which', 'gh'])->successful();
    }

    public function cliAuthenticated(): bool
    {
        return $this->run(['gh', 'auth', 'status'])->successful();
    }

    public function owners(): Collection
    {
        $user = $this->json(['gh', 'api', 'user']);
        $orgs = $this->json(['gh', 'api', 'user/orgs', '--paginate']);

        return collect([$user['login'] ?? null])
            ->merge(array_column($orgs, 'login'))
            ->filter()
            ->mapWithKeys(fn (string $owner) => [$owner => $owner]);
    }

    public function createRepository(string $name, string $owner, bool $private): ProcessResult
    {
        return $this->run([
            'gh', 'repo', 'create', $owner.'/'.$name,
            $private ? '--private' : '--public',
            '--source', '.',
            '--remote', 'origin',
        ]);
    }
}
