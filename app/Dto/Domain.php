<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class Domain extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $domain,
        public readonly string $status,
        public readonly bool $isPrimary,
        public readonly ?string $verificationStatus = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $updatedAt = null,
        public readonly ?string $environmentId = null,
    ) {
        //
    }

    public static function fromJsonApi(array $response): self
    {
        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];
        $relationships = $data['relationships'] ?? [];

        $transformed = [
            'id' => $data['id'],
            'domain' => $attributes['domain'] ?? '',
            'status' => $attributes['status'] ?? '',
            'isPrimary' => $attributes['is_primary'] ?? false,
            'verificationStatus' => $attributes['verification_status'] ?? null,
            'createdAt' => $attributes['created_at'] ?? null,
            'updatedAt' => $attributes['updated_at'] ?? null,
        ];

        if (isset($relationships['environment']['data']['id'])) {
            $transformed['environmentId'] = $relationships['environment']['data']['id'];
        }

        return self::from($transformed);
    }
}
