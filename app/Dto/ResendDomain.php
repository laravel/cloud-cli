<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class ResendDomain extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $region,
        public readonly string $status,
        public readonly array $records = [],
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $lastVerifiedAt = null,
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
            'name' => $attributes['name'],
            'region' => $attributes['region'],
            'status' => $attributes['status'],
            'records' => $attributes['records'] ?? [],
            'lastVerifiedAt' => $attributes['last_verified_at'] ?? null,
            'createdAt' => $attributes['created_at'] ?? null,
        ]);
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }
}
