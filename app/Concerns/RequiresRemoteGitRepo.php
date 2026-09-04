<?php

namespace App\Concerns;

use App\Exceptions\CommandExitException;
use App\Git;
use App\SourceProviders\CreatesRepositories;
use App\SourceProviders\SourceProviderManager;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

trait RequiresRemoteGitRepo
{
    protected function ensureRemoteGitRepo(): void
    {
        $git = app(Git::class);

        if ($git->hasRemote()) {
            return;
        }

        if (! $this->isInteractive()) {
            throw new RuntimeException('This directory has no Git remote. A Git repository is required to deploy to Laravel Cloud.');
        }

        $creators = app(SourceProviderManager::class)->repositoryCreators();

        if ($creators === []) {
            warning('This directory has no Git remote. A Git repository is required to deploy to Laravel Cloud.');
            warning('Create one and add it as a remote, or sign in to the GitHub CLI (gh) or GitLab CLI (glab) to create it from here.');

            throw new CommandExitException(1);
        }

        if ($git->isRepo()) {
            $createRepo = confirm(
                label: 'No Git remote found. Would you like to create a repository?',
                default: true,
            );

            if (! $createRepo) {
                throw new CommandExitException(0);
            }
        } else {
            $createRepo = confirm(
                label: 'This directory is not a Git repository. Would you like to create one?',
                default: true,
            );

            if (! $createRepo) {
                warning('Your codebase must be in a Git repository in order to deploy to Laravel Cloud.');

                throw new CommandExitException(0);
            }

            $git->initRepo();
            info('Git repository initialized.');
        }

        $driver = $this->selectSourceProvider($creators);

        $owner = $this->selectRepositoryOwner($driver);

        $repoName = text(
            label: 'Repository name',
            default: $git->currentDirectoryName(),
            required: true,
        );

        $visibility = select(
            label: 'Repository visibility',
            options: [
                'private' => 'Private',
                'public' => 'Public',
            ],
            default: 'private',
        );

        $result = $driver->createRepository($repoName, $owner, $visibility === 'private');

        if (! $result->successful()) {
            error('Failed to create repository: '.$result->errorOutput());

            throw new CommandExitException(1);
        }

        info('Repository created: '.$driver->repositoryUrl($owner.'/'.$repoName));

        $shouldCommit = confirm(
            label: 'Would you like to add, commit, and push your files?',
            default: true,
        );

        if (! $shouldCommit) {
            return;
        }

        $commitMessage = text(
            label: 'Commit message',
            default: 'first commit',
            required: true,
        );

        $git->addAll();

        $commitResult = $git->commit($commitMessage);

        if (! $commitResult->successful()) {
            error('Failed to commit: '.$commitResult->errorOutput());

            throw new CommandExitException(1);
        }

        info('Files committed successfully.');

        $pushResult = $git->push();

        if (! $pushResult->successful()) {
            error('Failed to push: '.$pushResult->errorOutput());

            throw new CommandExitException(1);
        }

        info('Pushed to '.$driver->provider()->label().' successfully.');
    }

    /**
     * @param  array<string, CreatesRepositories>  $creators
     */
    protected function selectSourceProvider(array $creators): CreatesRepositories
    {
        if (count($creators) === 1) {
            $driver = reset($creators);

            info('Using '.$driver->provider()->label().'.');

            return $driver;
        }

        return $creators[select(
            label: 'Where should the repository be created?',
            options: array_map(fn (CreatesRepositories $driver) => $driver->provider()->label(), $creators),
        )];
    }

    protected function selectRepositoryOwner(CreatesRepositories $driver): string
    {
        $owners = $driver->owners();

        if ($owners->isEmpty()) {
            error('Could not read any accounts from '.$driver->cliName().'.');

            throw new CommandExitException(1);
        }

        if ($owners->count() === 1) {
            $owner = $owners->first();

            info('Using account: '.$owner);

            return $owner;
        }

        return select(
            label: 'Which account should own this repository?',
            options: $owners->all(),
            default: $owners->first(),
        );
    }
}
