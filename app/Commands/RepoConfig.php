<?php

namespace App\Commands;

use App\Concerns\HasAClient;
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
    use HasAClient;

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

        if (! $organization) {
            error('No organization selected');

            return self::FAILURE;
        }

        $application = $this->selectApplication($localConfig->get('application_id'));

        $newValues = ['organization_id' => $organization->id];

        if ($application) {
            $newValues['application_id'] = $application->id;
        }

        $localConfig->setMany($newValues);

        outro('Configuration saved to '.$localConfig->path());

        return 0;
    }

    protected function resolveOrganization(): ?Organization
    {
        // TODO: Refactor once we have proper endpoints for orgs
        return spin(
            fn () => $this->client->listApplications()->data[0]?->organization ?? null,
            'Fetching organization...',
        );
    }

    protected function selectApplication($currentApplicationId): ?Application
    {
        $applications = spin(
            fn () => collect($this->client->listApplications()->data),
            'Fetching applications...',
        );

        if ($applications->isEmpty()) {
            warning('No applications found for this organization');

            return null;
        }

        if ($applications->containsOneItem()) {
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
            ]),
        );

        return $applications->firstWhere('id', $selected);
    }
}
