<?php

namespace App\Concerns;

use App\Git;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

trait RequiresRemoteGitRepo
{
    protected function ensureRemoteGitRepo(): void
    {
        $git = app(Git::class);

        if ($git->hasGitHubRemote()) {
            return;
        }

        if (! $git->ghInstalled() || ! $git->ghAuthenticated()) {
            warning('This directory is not a Git repository. A Git repository is required to deploy to Laravel Cloud.');

            exit(1);
        }

        if ($git->isRepo()) {
            $createRepo = confirm(
                label: 'No GitHub remote found. Would you like to create a GitHub repository?',
                default: true,
            );

            if (! $createRepo) {
                exit(0);
            }
        } else {
            $createRepo = confirm(
                label: 'This directory is not a Git repository. Would you like to create one?',
                default: true,
            );

            if (! $createRepo) {
                warning('Your codebase must be in a Git repository in order to deploy to Laravel Cloud.');

                exit(0);
            }

            $git->initRepo();
            info('Git repository initialized.');
        }

        $username = $git->getGitHubUsername();
        $orgs = $git->getGitHubOrgs();

        $owners = collect([$username])->merge($orgs)->filter()->mapWithKeys(fn ($org) => [$org => $org]);

        if ($owners->count() === 1) {
            $owner = $owners->first();
            info('Using GitHub account: '.$owner);
        } else {
            $owner = select(
                label: 'Which GitHub account should own this repository?',
                options: $owners,
                default: $owners->first(),
            );
        }

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

        $result = $git->createGitHubRepo($repoName, $owner, $visibility === 'private');

        if (! $result->successful()) {
            error('Failed to create repository: '.$result->errorOutput());

            exit(1);
        }

        info("Repository created: https://github.com/{$owner}/{$repoName}");

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

            exit(1);
        }

        info('Files committed successfully.');

        $pushResult = $git->push();

        if (! $pushResult->successful()) {
            error('Failed to push: '.$pushResult->errorOutput());

            exit(1);
        }

        info('Pushed to GitHub successfully.');
    }
}
