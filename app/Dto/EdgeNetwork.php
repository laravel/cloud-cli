<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class EdgeNetwork extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $domain,
        public readonly string $tenancyType,
        public readonly string $status,
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
            'domain' => $attributes['domain'] ?? '—',
            'tenancyType' => $attributes['tenancy_type'] ?? '—',
            'status' => $attributes['status'] ?? '—',
            'createdAt' => $attributes['created_at'] ?? null,
        ]);
    }

    public function isShared(): bool
    {
        return $this->tenancyType === 'shared';
    }
}
