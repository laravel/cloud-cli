<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class UsageCache extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $identifier,
        public readonly string $type,
        public readonly string $storage,
        public readonly int|float $computeHours,
        public readonly ?int $computeCents,
        public readonly int $totalCents,
    ) {
        //
    }

    public static function fromApiResponse(array $item): self
    {
        return new self(
            name: $item['name'] ?? '',
            identifier: $item['identifier'] ?? '',
            type: $item['type'] ?? '',
            storage: $item['storage'] ?? '',
            computeHours: $item['compute_hours'] ?? 0,
            computeCents: $item['compute_cents'] ?? null,
            totalCents: $item['total_cents'] ?? 0,
        );
    }
}
