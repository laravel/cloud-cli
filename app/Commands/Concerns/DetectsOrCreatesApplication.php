<?php

namespace App\Commands\Concerns;

use App\Client\Requests\CreateApplicationRequestData;
use App\Dto\Application;
use App\Dto\Region;
use App\Git;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

trait DetectsOrCreatesApplication
{
    /**
     * Detect existing applications for this repository and let the user choose.
     *
     * Returns an array with two elements:
     *   - 'application': The Application to create/deploy, or null
     *   - 'existing': true if the user chose to deploy an existing app, false if creating new
     *
     * @return array{application: Application|null, existing: bool}
     */
    protected function detectOrCreateApplication(Git $git): array
    {
        $repository = $git->remoteRepo();

        $applications = spin(
            fn () => $this->client->applications()->withDefaultIncludes()->list(),
            'Checking for existing application...',
        );

        $existingApps = $applications->collect()->filter(
            fn (Application $app) => $app->repositoryFullName === $repository,
        );

        $mostUsedRegion = $applications->collect()->pluck('region')->countBy()->sortDesc()->keys()->first();
        $defaultRegion = $mostUsedRegion ?? 'us-east-2';

        if ($existingApps->isEmpty()) {
            $application = $this->isInteractive()
                ? $this->loopUntilValid(fn () => $this->createApplicationInteractively($defaultRegion, $repository, $git))
                : $this->createApplicationNonInteractively($repository, $defaultRegion);

            return ['application' => $application, 'existing' => false];
        }

        if (! $this->isInteractive()) {
            $this->outputErrorOrThrow(
                'Repository already has an application. Use deploy <application-id> to deploy. Existing: '.$existingApps->pluck('id')->join(', '),
            );
        }

        info('Found '.$existingApps->count().' existing '.str('application')->plural($existingApps->count()).' for this repository.');

        $options = $existingApps
            ->mapWithKeys(fn (Application $app) => [$app->id => 'Deploy '.$app->name])
            ->collect()
            ->prepend('Create new application', 'new');

        $selectedApp = select(
            label: 'Application',
            options: $options->toArray(),
        );

        if ($selectedApp !== 'new') {
            return ['application' => $existingApps->firstWhere('id', $selectedApp), 'existing' => true];
        }

        $application = $this->isInteractive()
            ? $this->loopUntilValid(fn () => $this->createApplicationInteractively($defaultRegion, $repository, $git))
            : $this->createApplicationNonInteractively($repository, $defaultRegion);

        return ['application' => $application, 'existing' => false];
    }

    protected function createApplicationNonInteractively(string $repository, string $defaultRegion): Application
    {
        $name = $this->option('name') ?? str($repository)->afterLast('/')->toString();
        $region = $this->option('region') ?? $defaultRegion;

        return $this->client->applications()->create(
            new CreateApplicationRequestData(
                repository: $repository,
                name: $name,
                region: $region,
            ),
        );
    }

    protected function createApplicationInteractively(string $defaultRegion, string $repository, Git $git): ?Application
    {
        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(fn ($value) => text(
                label: 'Application name',
                default: $value ?? str($git->remoteRepo())->afterLast('/')->toString(),
                required: true,
            )),
        );

        $this->form()->prompt(
            'region',
            fn ($resolver) => $resolver->fromInput(function ($value) use ($defaultRegion) {
                $regions = spin(
                    fn () => $this->client->meta()->regions(),
                    'Fetching regions...',
                );

                return select(
                    label: 'Region',
                    options: collect($regions)->mapWithKeys(
                        fn (Region $region) => [
                            $region->value => $region->label,
                        ],
                    )->toArray(),
                    default: $value ?? $defaultRegion,
                );
            }),
        );

        return dynamicSpinner(
            fn () => $this->client->applications()->create(
                new CreateApplicationRequestData(
                    repository: $repository,
                    name: $this->form()->get('name'),
                    region: $this->form()->get('region'),
                ),
            ),
            'Creating application',
        );
    }
}
