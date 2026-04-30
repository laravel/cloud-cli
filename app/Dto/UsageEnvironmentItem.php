<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class UsageEnvironmentItem extends Data
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $type,
        public readonly string $computeProfile,
        public readonly string $computeDescription,
        public readonly int|float $cpuHours,
        public readonly int $totalCents,
    ) {
        //
    }

    public static function fromApiResponse(array $item): self
    {
        return new self(
            identifier: $item['identifier'] ?? '',
            type: $item['type'] ?? '',
            computeProfile: $item['compute_profile'] ?? '',
            computeDescription: $item['compute_description'] ?? '',
            cpuHours: $item['cpu_hours'] ?? 0,
            totalCents: $item['total_cents'] ?? 0,
        );
    }
}
