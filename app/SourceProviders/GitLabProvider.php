<?php

namespace App\SourceProviders;

use App\Enums\SourceProvider;
use App\SourceProviders\Concerns\RunsCli;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Collection;

class GitLabProvider implements CreatesRepositories, SourceProviderDriver
{
    use RunsCli;

    public function provider(): SourceProvider
    {
        return SourceProvider::GITLAB;
    }

    public function baseUrl(): string
    {
        return 'https://gitlab.com';
    }

    public function repositoryUrl(string $repository): string
    {
        return $this->baseUrl().'/'.$repository;
    }

    public function commitUrl(string $repository, string $commitHash): string
    {
        return $this->repositoryUrl($repository).'/-/commit/'.$commitHash;
    }

    public function branchUrl(string $repository, string $branchName): string
    {
        return $this->repositoryUrl($repository).'/-/tree/'.$branchName;
    }

    public function cliName(): string
    {
        return 'glab';
    }

    public function cliInstalled(): bool
    {
        return $this->run(['which', 'glab'])->successful();
    }

    public function cliAuthenticated(): bool
    {
        return $this->run(['glab', 'auth', 'status', ...$this->hostArgs()])->successful();
    }

    public function owners(): Collection
    {
        $user = $this->json(['glab', 'api', 'user', ...$this->hostArgs()]);

        // Developer is the lowest role that can be granted project creation in a group.
        $groups = $this->json(['glab', 'api', 'groups?min_access_level=30', '--paginate', ...$this->hostArgs()]);

        return collect([$user['username'] ?? null])
            ->merge(array_column($groups, 'full_path'))
            ->filter()
            ->mapWithKeys(fn (string $owner) => [$owner => $owner]);
    }

    public function createRepository(string $name, string $owner, bool $private): ProcessResult
    {
        return $this->run([
            'glab', 'repo', 'create', $this->projectPath($owner, $name),
            // GitLab projects default to internal, so visibility is always explicit.
            $private ? '--private' : '--public',
            '--remoteName', 'origin',
        ]);
    }

    protected function projectPath(string $owner, string $name): string
    {
        return $owner.'/'.$name;
    }

    /**
     * `glab` picks its host from the current directory's remote, which is no help before
     * a remote exists. Only a self-hosted instance needs to say which one it means.
     */
    protected function hostArgs(): array
    {
        return [];
    }
}
