<?php

namespace App\Concerns;

use function Laravel\Prompts\spin;

trait DeterminesDefaultRegion
{
    protected ?string $defaultRegion = null;

    protected function getDefaultRegion(): ?string
    {
        return $this->defaultRegion ??= $this->fetchDefaultRegion();
    }

    protected function fetchDefaultRegion(): ?string
    {
        $applications = spin(
            fn () => $this->client->applications()->list(),
            'Fetching applications...',
        );

        $mostUsedRegion = $applications->collect()
            ->pluck('region')
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        return $mostUsedRegion ?? 'us-east-2';
    }
}
