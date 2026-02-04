<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class Cache extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $name,
        public readonly string $status,
        public readonly string $region,
        public readonly string $size,
        public readonly bool $autoUpgradeEnabled,
        public readonly bool $isPublic,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly array $connection = [],
        public readonly array $environmentIds = [],
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];
        $relationships = $data['relationships'] ?? [];

        $transformed = [
            'id' => $data['id'],
            'type' => $attributes['type'] ?? '',
            'name' => $attributes['name'] ?? '',
            'status' => $attributes['status'] ?? 'unknown',
            'region' => $attributes['region'] ?? '',
            'size' => $attributes['size'] ?? '',
            'autoUpgradeEnabled' => $attributes['auto_upgrade_enabled'] ?? false,
            'isPublic' => $attributes['is_public'] ?? false,
            'createdAt' => $attributes['created_at'] ?? null,
            'connection' => $attributes['connection'] ?? [],
        ];

        if (isset($relationships['environments']['data'])) {
            $transformed['environmentIds'] = array_column($relationships['environments']['data'], 'id');
        }

        return self::from($transformed);
    }
}
