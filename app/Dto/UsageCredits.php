<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class UsageCredits extends Data
{
    public function __construct(
        public readonly int $usedCents,
        public readonly int $totalCents,
    ) {
        //
    }

    public static function fromApiResponse(array $item): self
    {
        return new self(
            usedCents: $item['used_cents'] ?? 0,
            totalCents: $item['total_cents'] ?? 0,
        );
    }
}
