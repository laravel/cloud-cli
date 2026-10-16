<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class DedicatedCluster extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $region,
        public readonly string $status,
        public readonly ?string $type = null,
        public readonly ?string $tenancyType = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];

        return self::from([
            'id' => $data['id'],
            'name' => $attributes['name'] ?? $data['id'],
            'region' => $attributes['region'] ?? '—',
            'status' => $attributes['status'] ?? '—',
            'type' => $attributes['type'] ?? null,
            'tenancyType' => $attributes['tenancy_type'] ?? null,
            'createdAt' => $attributes['created_at'] ?? null,
        ]);
    }
}
