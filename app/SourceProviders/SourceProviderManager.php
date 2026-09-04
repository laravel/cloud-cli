<?php

namespace App\SourceProviders;

use App\Enums\SourceProvider;
use App\Exceptions\UnsupportedSourceProviderException;
use App\Git;

class SourceProviderManager
{
    public function __construct(protected Git $git)
    {
        //
    }

    public function driver(SourceProvider $provider, ?string $host = null): SourceProviderDriver
    {
        return match ($provider) {
            SourceProvider::GITHUB => new GitHubProvider,
            SourceProvider::GITLAB => new GitLabProvider,
            SourceProvider::GITLAB_SELF_HOSTED => new GitLabSelfHostedProvider(
                $host ?? $this->git->remoteHost() ?? throw new UnsupportedSourceProviderException(
                    'Could not work out which GitLab instance this repository lives on.',
                ),
            ),
            SourceProvider::BITBUCKET => throw new UnsupportedSourceProviderException(
                $provider->label().' is not supported by the CLI yet.',
            ),
        };
    }

    /**
     * The provider behind the current directory's origin remote, if we recognise the host.
     */
    public function detect(): ?SourceProvider
    {
        return SourceProvider::fromHost($this->git->remoteHost());
    }

    /**
     * The API does not tell us which provider an application uses, so the only hint we
     * have is the remote in the current directory. An application belonging to some
     * other repository falls back to GitHub and may well be wrong.
     *
     * Delete this once the repository attributes carry the provider type.
     */
    public function forRepository(?string $repositoryFullName): SourceProviderDriver
    {
        $provider = $repositoryFullName !== null && $repositoryFullName === $this->git->remoteRepo()
            ? $this->detect()
            : null;

        return $this->driver($provider ?? SourceProvider::GITHUB);
    }

    /**
     * Providers that can create a repository right now. Self-hosted GitLab is missing on
     * purpose: with no remote yet, there is no host to point `glab` at.
     *
     * @return array<string, CreatesRepositories>
     */
    public function repositoryCreators(): array
    {
        return collect([new GitHubProvider, new GitLabProvider])
            ->filter(fn (CreatesRepositories $driver) => $driver->cliInstalled() && $driver->cliAuthenticated())
            ->keyBy(fn (CreatesRepositories $driver) => $driver->provider()->value)
            ->all();
    }
}
