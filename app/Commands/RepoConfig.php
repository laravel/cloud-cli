<?php

namespace App\Commands;

use App\Dto\Application;
use App\Dto\Organization;
use App\Git;
use App\LocalConfig;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class RepoConfig extends BaseCommand
{
    protected $signature = 'repo:config
                            {application? : The application ID or name to use as the default}
                            {--organization= : The organization ID, name, or slug to use as the default}';

    protected $description = 'Configure Laravel Cloud defaults for the current repository';

    public function handle(Git $git, LocalConfig $localConfig)
    {
        intro('Configure Repository Defaults');

        if (! $git->isRepo()) {
            $this->failAndExit('This directory is not a Git repository.');
        }

        $gitRoot = $git->getRoot();

        if (! $gitRoot) {
            $this->failAndExit('Could not determine Git repository root.');
        }

        $this->ensureClient(ignoreLocalConfig: true, organization: $this->option('organization'));

        $organization = $this->resolveOrganization();

        $application = $this->selectApplication($localConfig->get('application_id'));

        $newValues = ['organization_id' => $organization->id];

        $newValues['application_id'] = $application->id;

        $localConfig->setMany($newValues);

        outro('Configuration saved to '.$localConfig->path());

        return 0;
    }

    protected function resolveOrganization(): Organization
    {
        $organization = spin(
            fn () => $this->client->meta()->organization(),
            'Fetching organization...',
        );

        // With a single API token there is nothing to choose between, so --organization
        // has been ignored up to this point. Check it against what the token belongs to.
        $requested = $this->option('organization');

        if ($requested && ! in_array($requested, [$organization->id, $organization->name, $organization->slug], true)) {
            $this->failAndExit("Unable to resolve organization [{$requested}]. This API token belongs to {$organization->name}. Run `cloud auth:token --list` to see your tokens.");
        }

        return $organization;
    }

    protected function selectApplication($currentApplicationId): Application
    {
        $applications = spin(
            fn () => $this->client->applications()->withDefaultIncludes()->list()->collect(),
            'Fetching applications...',
        );

        if ($applications->isEmpty()) {
            $this->failAndExit('No applications found for this organization.');
        }

        if ($identifier = $this->argument('application')) {
            $app = $applications->firstWhere('id', $identifier) ?? $applications->firstWhere('name', $identifier);

            if (! $app) {
                $this->failAndExit("Unable to resolve application [{$identifier}]. Run `cloud application:list --json -n` to see available applications.");
            }

            answered(label: 'Application', answer: $app->name);

            return $app;
        }

        if ($applications->hasSole()) {
            $app = $applications->first();

            answered(label: 'Application', answer: $app->name);

            return $app;
        }

        if (! $this->isInteractive()) {
            $this->failAndExit('Multiple applications found. Provide an application ID or name: `cloud repo:config {application} -n`.');
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
