<?php

namespace App\Concerns;

use App\Dto\Application;
use Illuminate\Support\Collection;

use function Laravel\Prompts\select;

trait RequiresApplication
{
    /**
     * @param  Collection<Application>  $apps
     */
    protected function getApplication(Collection $apps): Application
    {
        if ($this->argument('application')) {
            $app = $apps->firstWhere('id', $this->argument('application'));
            answered(label: 'Application', answer: "{$app->name}");

            return $app;
        }

        if ($apps->count() === 1) {
            $app = $apps->first();
            answered(label: 'Application', answer: "{$app->name}");

            return $app;
        }

        $selectedApp = select(
            label: 'Select an application',
            options: $apps->mapWithKeys(fn ($app) => [$app->id => $app->name]),
        );

        return $apps->firstWhere('id', $selectedApp);
    }
}
