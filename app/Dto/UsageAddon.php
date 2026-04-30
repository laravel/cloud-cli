<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class UsageAddon extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly int $totalCents,
    ) {
        //
    }

    public static function fromApiResponse(array $item): self
    {
        return new self(
            name: $item['name'] ?? '',
            totalCents: $item['total_cents'] ?? 0,
        );
    }
}
