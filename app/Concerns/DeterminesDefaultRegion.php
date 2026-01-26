<?php

namespace App\Concerns;

use App\Enums\CloudRegion;

use function Laravel\Prompts\spin;

trait DeterminesDefaultRegion
{
    protected ?string $defaultRegion = null;

    protected function getDefaultRegion(): ?string
    {
        if ($this->defaultRegion) {
            return $this->defaultRegion;
        }

        $applications = spin(
            fn () => $this->client->listApplications(),
            'Fetching applications...',
        );

        $mostUsedRegion = collect($applications->data)
            ->pluck('region')
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        $this->defaultRegion = CloudRegion::tryFrom($mostUsedRegion ?? '')?->value ?? CloudRegion::US_EAST_2->value;

        return $this->defaultRegion;
    }
}
