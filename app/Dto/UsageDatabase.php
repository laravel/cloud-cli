<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class UsageDatabase extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $identifier,
        public readonly string $type,
        public readonly int|float $storageGb,
        public readonly ?int $storageCents,
        public readonly int|float $computeUnits,
        public readonly string $computeUnitLabel,
        public readonly ?int $computeCents,
        public readonly int|float $backupsGb,
        public readonly ?int $backupsCents,
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
            storageGb: $item['storage_gb'] ?? 0,
            storageCents: $item['storage_cents'] ?? null,
            computeUnits: $item['compute_units'] ?? 0,
            computeUnitLabel: $item['compute_unit_label'] ?? '',
            computeCents: $item['compute_cents'] ?? null,
            backupsGb: $item['backups_gb'] ?? 0,
            backupsCents: $item['backups_cents'] ?? null,
            totalCents: $item['total_cents'] ?? 0,
        );
    }
}
