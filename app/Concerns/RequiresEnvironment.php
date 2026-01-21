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

        if ($environments->containsOneItem()) {
            $environment = $environments->first();
            answered(label: 'Environment', answer: "{$environment->name}");

            return $environment;
        }

        $selection = select(
            label: 'Environment',
            options: $environments->mapWithKeys(fn ($env) => [$env->id => $env->name]),
        );

        return $environments->firstWhere('id', $selection);
    }
}
