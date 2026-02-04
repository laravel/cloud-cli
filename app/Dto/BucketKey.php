<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class BucketKey extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $permission,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?string $accessKeyId = null,
        public readonly ?string $secretAccessKey = null,
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];

        return self::from([
            'id' => $data['id'],
            'name' => $attributes['name'] ?? '',
            'permission' => $attributes['permission'] ?? 'read_write',
            'createdAt' => $attributes['created_at'] ?? null,
            'accessKeyId' => $attributes['access_key_id'] ?? null,
            'secretAccessKey' => $attributes['secret_access_key'] ?? null,
        ]);
    }
}
