<?php

namespace App\Concerns;

use App\Dto\Environment;
use Illuminate\Support\Collection;

use function Laravel\Prompts\select;

trait RequiresEnvironment
{
    /**
     * @param  Collection<Environment>  $environments
     */
    protected function getEnvironment(Collection $environments): Environment
    {
        if ($this->argument('environment')) {
            $environment = $environments->firstWhere('name', $this->argument('environment'));
            answered(label: 'Environment', answer: "{$environment->name}");

            return $environment;
        }

        if (count($environments) === 1) {
            $environment = $environments[0];
            answered(label: 'Environment', answer: "{$environment->name}");

            return $environment;
        }

        $selection = select(
            label: 'Select an environment',
            options: $environments->mapWithKeys(fn ($env) => [$env->id => $env->name]),
        );

        return $environments->firstWhere('id', $selection);
    }
}
