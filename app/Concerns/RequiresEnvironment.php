<?php

namespace App\Concerns;

use App\Dto\Environment;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function Laravel\Prompts\select;

trait RequiresEnvironment
{
    /**
     * @param  Collection<Environment>  $environments
     */
    protected function getEnvironment(Collection|LazyCollection $environments): Environment
    {
        $environmentArg = $this->hasArgument('environment') ? $this->argument('environment') : null;

        if ($environmentArg) {
            $environment = $environments->firstWhere('name', $environmentArg);
            answered(label: 'Environment', answer: "{$environment->name}");

            return $environment;
        }

        if ($environments->hasSole()) {
            $environment = $environments->first();
            answered(label: 'Environment', answer: "{$environment->name}");

            return $environment;
        }

        $selection = select(
            label: 'Environment',
            options: $environments->mapWithKeys(fn ($env) => [$env->id => $env->name])->toArray(),
        );

        return $environments->firstWhere('id', $selection);
    }
}
