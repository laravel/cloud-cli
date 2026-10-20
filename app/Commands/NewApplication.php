<?php

namespace App\Commands;

use App\Client\Requests\CreateApplicationRequestData;
use App\Client\Requests\InitiateDeploymentRequestData;
use App\Concerns\CreatesDatabase;
use App\Concerns\CreatesDatabaseCluster;
use App\Concerns\CreatesWebSocketApplication;
use App\Concerns\CreatesWebSocketCluster;
use App\Concerns\DeterminesDefaultRegion;
use App\Concerns\ProvisionsDefaultResources;
use App\Concerns\WatchesDeployments;
use App\Dto\Application;
use App\Dto\Deployment;
use App\Dto\Environment;
use App\Dto\Region;
use App\Enums\SourceProvider;
use App\Enums\StarterKit;
use App\Git;
use App\LocalConfig;
use App\SourceProviders\GitHubProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\autocomplete;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class NewApplication extends BaseCommand
{
    use CreatesDatabase;
    use CreatesDatabaseCluster;
    use CreatesWebSocketApplication;
    use CreatesWebSocketCluster;
    use DeterminesDefaultRegion;
    use ProvisionsDefaultResources;
    use WatchesDeployments;

    protected $signature = 'new
                            {--starter-kit= : Starter kit (laravel, laravel-react, laravel-vue, laravel-livewire, laravel-svelte, nextjs, nuxt, express, go, django, fastapi, rails)}
                            {--repository= : New GitHub repository to create (owner/name). Must not exist yet}
                            {--name= : Application name. Default: the repository name}
                            {--region= : Region. Default: most-used or us-east-2}
                            {--path= : Where to clone the repository. Default: ./<repository name>}
                            {--database= : Database type or alias (postgres, postgres<version>, mysql, none, or a type from `cloud database:types`). Default: postgres for Laravel kits, none otherwise}
                            {--database-preset= : Preset for the database, as listed by the API for the type and region (e.g. dev, prod, scale — case-insensitive). Default: dev, or the first preset offered}
                            {--cluster= : Dedicated cluster ID to deploy to (see dedicated-cluster:list)}';

    protected $description = 'Create, deploy, and clone a new application from a starter kit';

    protected string $path;

    public function handle(Git $git)
    {
        intro('Creating a New Application on Laravel Cloud');

        $this->ensureClient();

        $application = $this->loopUntilValid($this->createApplication(...));
        $starterKit = StarterKit::from($this->form()->get('starter_kit'));

        success('Application created!');

        $application = $this->client->applications()->withDefaultIncludes()->get($application->id);
        $this->appName = $application->name;
        $this->region = $application->region;

        $environment = $this->client->environments()->include('instances')->get($application->defaultEnvironmentId ?? '');

        $databaseSchemaId = $this->wantsDatabase($starterKit) ? $this->provisionDatabaseOpinionated() : null;

        $deployment = $this->watchFirstDeployment($environment);

        if ($databaseSchemaId !== null) {
            $this->attachToEnvironment($environment, $databaseSchemaId, null);

            // The first deployment started before the database existed, so it has none of its credentials.
            $deployment = $this->watchDeployment(
                $this->client->deployments()->initiate(new InitiateDeploymentRequestData($environment->id)),
            );
        }

        $isReady = false;

        if ($deployment?->failed()) {
            error('Deployment failed: '.$deployment->failureReason);
        } elseif ($deployment?->succeeded()) {
            $isReady = spin(
                fn () => $this->waitForUrlToBeReady($environment),
                'Waiting for site to be ready...',
            );

            if (! $isReady) {
                warning('The site is not responding yet: '.$environment->url);
            }
        }

        $cloned = $this->cloneRepository($git, $application);

        $result = [
            'application_id' => $application->id,
            'environment_id' => $environment->id,
            'deployment_id' => $deployment?->id,
            'deployment_status' => $deployment?->status->value,
            'url' => $environment->url,
            'repository' => $application->repositoryFullName,
            'path' => $cloned ? $this->path : null,
        ];

        if (! $deployment?->succeeded() || ! $cloned) {
            $this->writeJsonIfWanted($result);

            return self::FAILURE;
        }

        $this->outputJsonIfWanted($result);

        outro('Live at '.$environment->url.($isReady ? '' : ' (still starting)').'. Code is in '.$this->path);
    }

    protected function createApplication(): Application
    {
        $this->form()->prompt(
            'starter_kit',
            fn ($resolver) => $resolver->fromInput(fn (?string $value) => select(
                label: 'Starter kit',
                options: StarterKit::options(),
                default: $value ?? StarterKit::LARAVEL->value,
                scroll: 12,
            )),
        );

        $starterKit = $this->starterKitFrom($this->form()->get('starter_kit'));

        $this->form()->prompt(
            'repository',
            fn ($resolver) => $resolver->fromInput(function (?string $value) {
                $owners = $this->repositoryOwners();

                return autocomplete(
                    label: 'New GitHub repository',
                    options: fn (string $input) => $owners
                        ->map(fn (string $owner) => $owner.'/'.(str_contains($input, '/') ? Str::after($input, '/') : ''))
                        ->filter(fn (string $option) => Str::startsWith(strtolower($option), strtolower($input)))
                        ->values()
                        ->all(),
                    placeholder: 'owner/name',
                    default: $value ?? '',
                    required: true,
                    hint: 'Cloud creates this private repository, so it must not exist yet.',
                );
            }),
        );

        $repositoryName = str($this->form()->get('repository'))->afterLast('/')->toString();

        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => text(
                    label: 'Application name',
                    default: $value ?? $repositoryName,
                    required: true,
                ))
                ->nonInteractively(fn () => $repositoryName),
        );

        $this->form()->prompt(
            'region',
            fn ($resolver) => $resolver
                ->fromInput(function (?string $value) {
                    $regions = spin(
                        fn () => $this->client->meta()->regions(),
                        'Fetching regions...',
                    );

                    return select(
                        label: 'Region',
                        options: collect($regions)->mapWithKeys(fn (Region $region) => [
                            $region->value => $region->label,
                        ])->toArray(),
                        default: $value ?? $this->getDefaultRegion(),
                    );
                })
                ->nonInteractively(fn () => $this->getDefaultRegion()),
        );

        $this->form()->prompt(
            'path',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => text(
                    label: 'Clone to',
                    default: $value ?? './'.$repositoryName,
                    required: true,
                    validate: fn (string $path) => $this->pathIsUsable($this->absolutePath($path))
                        ? null
                        : 'That directory already exists and is not empty.',
                ))
                ->nonInteractively(fn () => './'.$repositoryName),
        );

        $this->path = $this->absolutePath($this->form()->get('path'));

        // Checked before creating anything, so a bad path cannot leave behind an app and repository.
        if (! $this->pathIsUsable($this->path)) {
            $this->failAndExit('Cannot clone into '.$this->path.': the directory already exists and is not empty.');
        }

        $this->form()->define('cluster_id', fn ($resolver) => $resolver, 'cluster');

        return spin(
            fn () => $this->client->applications()->create(
                new CreateApplicationRequestData(
                    repository: $this->form()->get('repository'),
                    name: $this->form()->get('name'),
                    region: $this->form()->get('region'),
                    sourceProvider: SourceProvider::GITHUB,
                    clusterId: $this->form()->get('cluster_id') ?: null,
                    starterKit: $starterKit,
                ),
            ),
            'Creating application and repository...',
        );
    }

    protected function starterKitFrom(?string $value): StarterKit
    {
        if ($value === null || $value === '') {
            $this->failAndExit('A starter kit is required. Pass --starter-kit with one of: '.implode(', ', array_keys(StarterKit::options())));
        }

        return StarterKit::tryFrom($value) ?? $this->failAndExit(
            'Unknown starter kit ['.$value.']. Use one of: '.implode(', ', array_keys(StarterKit::options())),
        );
    }

    /**
     * Owners of repositories the organization already deploys come first, because Cloud's
     * GitHub App is known to be installed for them. The GitHub CLI fills in the rest.
     *
     * @return Collection<int, string>
     */
    protected function repositoryOwners(): Collection
    {
        return spin(function () {
            $fromCloud = $this->client->applications()->list()->collect()->collect()
                ->pluck('repositoryFullName')
                ->filter()
                ->map(fn (string $repository) => Str::before($repository, '/'))
                ->countBy()
                ->sortDesc()
                ->keys();

            $github = new GitHubProvider;

            $fromGitHub = $github->cliInstalled() && $github->cliAuthenticated()
                ? $github->owners()->values()
                : collect();

            return $fromCloud->merge($fromGitHub)->unique()->values();
        }, 'Finding GitHub accounts...');
    }

    protected function wantsDatabase(StarterKit $starterKit): bool
    {
        $database = strtolower((string) $this->option('database'));

        if ($database === 'none') {
            return false;
        }

        return $database !== '' || $starterKit->isLaravel();
    }

    /**
     * Cloud starts the first deployment itself when it creates a starter kit application.
     */
    protected function watchFirstDeployment(Environment $environment): ?Deployment
    {
        $deployment = $this->client->deployments()->list($environment->id)->collect()->first();

        if ($deployment === null) {
            warning('Cloud did not start a deployment. Run `cloud deploy` from the cloned repository.');

            return null;
        }

        return $this->watchDeployment($deployment);
    }

    protected function cloneRepository(Git $git, Application $application): bool
    {
        $repository = $application->repositoryFullName ?? $this->form()->get('repository');

        $result = spin(
            fn () => $git->cloneGitHubRepository($repository, $this->path),
            'Cloning '.$repository.'...',
        );

        if (! $result->successful()) {
            error('Could not clone '.$repository.': '.trim($result->errorOutput()));

            return false;
        }

        (new LocalConfig($git, $this->path))->setMany([
            'organization_id' => $application->organizationId,
            'application_id' => $application->id,
        ]);

        info('Cloned into '.$this->path);

        return true;
    }

    protected function absolutePath(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            $path = rtrim((string) getenv('HOME'), '/').substr($path, 1);
        }

        if (! str_starts_with($path, '/')) {
            $path = getcwd().'/'.$path;
        }

        return rtrim(str_replace('/./', '/', $path), '/');
    }

    protected function pathIsUsable(string $path): bool
    {
        if (! File::exists($path)) {
            return true;
        }

        return File::isDirectory($path) && File::isEmptyDirectory($path, ignoreDotFiles: false);
    }
}
