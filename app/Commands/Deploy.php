<?php

namespace App\Commands;

use App\CloudClient;
use App\ConfigRepository;
use App\Dto\Application;
use App\Dto\Environment;
use App\Git;
use App\Prompts\DynamicSpinner;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class Deploy extends Command
{
    protected $signature = 'deploy';

    protected $description = 'Deploy the application to Laravel Cloud';

    protected array $regions = [
        'us-east-1' => 'US East (Virginia)',
        'us-east-2' => 'US East (Ohio)',
        'ca-central-1' => 'CA Central (Central)',
        'eu-central-1' => 'EU Central (Frankfurt)',
        'eu-west-1' => 'EU West (Ireland)',
        'eu-west-2' => 'EU West (London)',
        'ap-southeast-1' => 'Asia Pacific (Singapore)',
        'ap-southeast-2' => 'Asia Pacific (Sydney)',
    ];

    public function handle(ConfigRepository $config, Git $git)
    {
        $apiKey = $config->get('api_key');

        if (! $apiKey) {
            info('No API key found!');
            info('Learn how to generate a key: https://cloud.laravel.com/docs/api/authentication#create-an-api-token');

            $apiKey = password(
                label: 'Laravel Cloud API key',
                required: true,
            );

            $config->set('api_key', $apiKey);

            info('API key saved to ' . $config->path());
        }

        $this->ensureGitHubRepo($git);

        $client = new CloudClient($apiKey);

        $this->ensureCloudApplication($client, $git);
    }

    protected function ensureCloudApplication(CloudClient $client, Git $git): void
    {
        $repository = $git->remoteUrl();

        $applications = spin(
            fn() => $client->listApplications(),
            'Checking for existing application...'
        );

        $existingApp = collect($applications['data'] ?? [])
            ->map(fn($app) => Application::fromApiResponse($app))
            ->first(
                fn($app) => $app->repositoryFullName === $repository
            );

        if ($existingApp) {
            info("Found existing application: {$existingApp->name}");

            $environments = spin(
                fn() => $client->listEnvironments($existingApp->id),
                'Checking for existing environments...'
            );

            $defaultEnvironmentName = $git->getDefaultBranch();

            $existingEnvironment = collect($environments['data'] ?? [])
                ->map(fn($env) => Environment::fromApiResponse($env))
                ->first(fn($env) => $env->name === $defaultEnvironmentName);

            if ($existingEnvironment) {
                info("Found existing environment: {$existingEnvironment->name}");
                $deployment = $client->initiateDeployment($existingEnvironment->id);
                info("Deployment initiated at {$deployment->startedAt?->toDateTimeString()}");

                dump(['initial', $deployment]);

                (new DynamicSpinner('Deployingggg...'))->spin(function () use ($client, $deployment) {
                    do {
                        $deploymentStatus = $client->getDeployment($deployment->id);
                        dump($deploymentStatus);
                        sleep(1);
                    } while (! $deploymentStatus->isCompleted());
                });

                // spin(
                //     function () use ($client, $deployment) {
                //         do {
                //             $deploymentStatus = $client->getDeployment($deployment->id);
                //             dump($deploymentStatus);
                //             sleep(1);
                //         } while (! $deploymentStatus->isCompleted());
                //     },
                //     'Deploying...'
                // );
            } else {
                info('No existing environment found. Creating new environment...');

                $newEnvironment = spin(
                    fn() => $client->createEnvironment($existingApp->id, $defaultEnvironmentName),
                    'Creating new environment...'
                );

                info("Environment created: {$newEnvironment->name}");
            }

            return;
        }

        $createApp = confirm(
            label: 'No Laravel Cloud application found for this repository. Would you like to create one?',
            default: true,
        );

        if (! $createApp) {
            return;
        }

        $appName = text(
            label: 'Application name',
            default: $git->currentDirectoryName(),
            required: true,
        );

        // TODO: Default region from config
        $region = select(
            label: 'Select a region',
            options: $this->regions,
            default: 'us-east-2',
        );

        $application = spin(
            fn() => $client->createApplication($repository, $appName, $region),
            'Creating application...'
        );

        if (isset($application['data']['id'])) {
            info("Application created: {$application['data']['name']}");
        } else {
            error('Failed to create application: ' . ($application['message'] ?? 'Unknown error'));

            exit(1);
        }
    }

    protected function ensureGitHubRepo(Git $git): void
    {
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

        $owners = collect([$username])->merge($orgs)->filter()->mapWithKeys(fn($org) => [$org => $org]);

        if ($owners->count() === 1) {
            $owner = $owners->first();
            info('Using GitHub account: ' . $owner);
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
            error('Failed to create repository: ' . $result->errorOutput());

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
            error('Failed to commit: ' . $commitResult->errorOutput());

            exit(1);
        }

        info('Files committed successfully.');

        $pushResult = $git->push();

        if (! $pushResult->successful()) {
            error('Failed to push: ' . $pushResult->errorOutput());

            exit(1);
        }

        info('Pushed to GitHub successfully.');
    }
}
