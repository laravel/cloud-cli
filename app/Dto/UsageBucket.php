<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class UsageBucket extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $identifier,
        public readonly int $classARequestsCount,
        public readonly ?int $classARequestsCents,
        public readonly int $classBRequestsCount,
        public readonly ?int $classBRequestsCents,
        public readonly int|float $storageGb,
        public readonly ?int $storageCents,
        public readonly int $totalCents,
    ) {
        //
    }

    public static function fromApiResponse(array $item): self
    {
        return new self(
            name: $item['name'] ?? '',
            identifier: $item['identifier'] ?? '',
            classARequestsCount: $item['class_a_requests_count'] ?? 0,
            classARequestsCents: $item['class_a_requests_cents'] ?? null,
            classBRequestsCount: $item['class_b_requests_count'] ?? 0,
            classBRequestsCents: $item['class_b_requests_cents'] ?? null,
            storageGb: $item['storage_gb'] ?? 0,
            storageCents: $item['storage_cents'] ?? null,
            totalCents: $item['total_cents'] ?? 0,
        );
    }
}
