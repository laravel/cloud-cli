<?php

namespace App\Dto;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class DeploymentLogPhase extends Data
{
    public function __construct(
        public readonly bool $available = false,
        #[DataCollectionOf(DeploymentLogStep::class)]
        public readonly array $steps = [],
    ) {
        //
    }

    public static function createFromResponse(array $data): self
    {
        return new self(
            available: (bool) ($data['available'] ?? false),
            steps: array_map(
                fn (array $step) => DeploymentLogStep::createFromResponse($step),
                array_values($data['steps'] ?? []),
            ),
        );
    }
}
