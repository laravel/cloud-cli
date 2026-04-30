<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class UsageWebsocket extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $identifier,
        public readonly int $maxConnections,
        public readonly int|float $usageTimeHours,
        public readonly ?int $usageTimeCents,
        public readonly ?int $totalCents,
    ) {
        //
    }

    public static function fromApiResponse(array $item): self
    {
        return new self(
            name: $item['name'] ?? '',
            identifier: $item['identifier'] ?? '',
            maxConnections: $item['max_connections'] ?? 0,
            usageTimeHours: $item['usage_time_hours'] ?? 0,
            usageTimeCents: $item['usage_time_cents'] ?? null,
            totalCents: $item['total_cents'] ?? null,
        );
    }
}
