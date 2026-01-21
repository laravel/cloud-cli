<?php

namespace App\Concerns;

use App\Dto\Application;
use App\LocalConfig;
use Exception;
use Illuminate\Support\Collection;
use RuntimeException;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

trait RequiresApplication
{
    /**
     * @param  Collection<Application>  $apps
     */
    protected function getCloudApplication(?Collection $apps = null, $showPrompt = true): Application
    {
        $defaultApplicationId = $this->argument('application') ?? app(LocalConfig::class)->get('application_id');

        if ($defaultApplicationId) {
            $identifier = $defaultApplicationId;

            if ($apps) {
                $app = $this->getByNameOrId($identifier, $apps);
            } else {
                if (str_starts_with($identifier, 'app-')) {
                    try {
                        $app = spin(
                            fn () => $this->client->getApplication($identifier),
                            'Fetching application...'
                        );
                    } catch (Exception $e) {
                        $app = $this->getByNameOrId($identifier);
                    }
                } else {
                    $app = $this->getByNameOrId($identifier);
                }
            }

            if (! $app) {
                throw new RuntimeException("Application '{$identifier}' not found.");
            }

            $this->displayApplication($app, $showPrompt);

            return $app;
        }

        $apps ??= $this->fetchApplications();

        if ($apps->containsOneItem()) {
            $app = $apps->first();

            $this->displayApplication($app, $showPrompt);

            return $app;
        }

        $selectedApp = select(
            label: 'Application',
            options: $apps->mapWithKeys(fn ($app) => [$app->id => $app->name]),
        );

        return $apps->firstWhere('id', $selectedApp);
    }

    protected function getByNameOrId(string $identifier, ?Collection $apps = null): Application
    {
        $apps ??= $this->fetchApplications();

        return $apps->firstWhere('id', $identifier)
            ?? $apps->firstWhere('name', $identifier);
    }

    protected function displayApplication(Application $app, $showPrompt = true): void
    {
        if ($showPrompt) {
            answered(label: 'Application', answer: "{$app->name}");
        }
    }

    protected function fetchApplications(): Collection
    {
        return collect(spin(
            fn () => $this->client->listApplications(),
            'Fetching applications...'
        )->data);
    }
}
