<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class UsageBandwidth extends Data
{
    public function __construct(
        public readonly int $costCents,
        public readonly int|float $usagePercentage,
        public readonly int $allowanceBytes,
    ) {
        //
    }

    public static function fromApiResponse(array $item): self
    {
        return new self(
            costCents: $item['cost_cents'] ?? 0,
            usagePercentage: $item['usage_percentage'] ?? 0,
            allowanceBytes: $item['allowance_bytes'] ?? 0,
        );
    }
}
