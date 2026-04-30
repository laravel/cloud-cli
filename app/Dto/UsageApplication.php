<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class UsageApplication extends Data
{
    public function __construct(
        public readonly string $identifier,
        public readonly int $totalCostCents,
    ) {
        //
    }

    public static function fromApiResponse(array $item): self
    {
        return new self(
            identifier: $item['identifier'] ?? '',
            totalCostCents: $item['total_cost_cents'] ?? 0,
        );
    }
}
