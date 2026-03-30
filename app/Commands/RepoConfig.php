<?php

namespace App\Commands;

use App\Dto\Application;
use App\Dto\Organization;
use App\Git;
use App\LocalConfig;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class RepoConfig extends BaseCommand
{
    protected $signature = 'repo:config';

    protected $description = 'Configure Laravel Cloud defaults for the current repository';

    public function handle(Git $git, LocalConfig $localConfig)
    {
        intro('Configure Repository Defaults');

        if (! $git->isRepo()) {
            error('This directory is not a Git repository.');

            return self::FAILURE;
        }

        $gitRoot = $git->getRoot();

        if (! $gitRoot) {
            error('Could not determine Git repository root.');

            return self::FAILURE;
        }

        $this->ensureClient(ignoreLocalConfig: true);

        $organization = $this->resolveOrganization();

        $application = $this->selectApplication($localConfig->get('application_id'));

        if ($application === null) {
            return self::FAILURE;
        }

        $newValues = ['organization_id' => $organization->id];

        $newValues['application_id'] = $application->id;

        $localConfig->setMany($newValues);

        outro('Configuration saved to '.$localConfig->path());

        return 0;
    }

    protected function resolveOrganization(): Organization
    {
        return spin(
            fn () => $this->client->meta()->organization(),
            'Fetching organization...',
        );
    }

    protected function selectApplication($currentApplicationId): ?Application
    {
        $applications = spin(
            fn () => $this->client->applications()->withDefaultIncludes()->list()->collect(),
            'Fetching applications...',
        );

        if ($applications->isEmpty()) {
            warning('No applications found for this organization');

            return null;
        }

        if ($applications->hasSole()) {
            $app = $applications->first();

            answered(label: 'Application', answer: $app->name);

            return $app;
        }

        $defaultApplicationId = $applications->firstWhere('id', $currentApplicationId)?->id;

        $selected = select(
            label: 'Application',
            default: $defaultApplicationId,
            options: $applications->mapWithKeys(fn ($application) => [
                $application->id => $application->id === $currentApplicationId
                    ? $application->name.$this->dim(' (current default)')
                    : $application->name,
            ])->toArray(),
        );

        return $applications->firstWhere('id', $selected);
    }
}
