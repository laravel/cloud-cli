<?php

namespace App\Dto;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class DeploymentLogPhase extends Data
{
    public function __construct(
        public readonly bool $available,
        #[DataCollectionOf(DeploymentLogStep::class)]
        public readonly array $steps = [],
    ) {
        //
    }

    public static function fromApiResponse(array $phase): self
    {
        return new self(
            available: (bool) ($phase['available'] ?? false),
            steps: array_map(
                fn (array $step) => DeploymentLogStep::fromApiResponse($step),
                $phase['steps'] ?? [],
            ),
        );
    }
}
