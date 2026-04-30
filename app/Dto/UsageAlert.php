<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class UsageAlert extends Data
{
    public function __construct(
        public readonly int $thresholdCents,
        public readonly int|float $remainingPercentage,
    ) {
        //
    }

    public static function fromApiResponse(array $item): self
    {
        return new self(
            thresholdCents: $item['threshold_cents'] ?? 0,
            remainingPercentage: $item['remaining_percentage'] ?? 0,
        );
    }
}
