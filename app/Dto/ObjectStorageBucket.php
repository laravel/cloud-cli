<?php

namespace App\Dto;

use App\Enums\FilesystemJurisdiction;
use App\Enums\FilesystemStatus;
use App\Enums\FilesystemType;
use App\Enums\FilesystemVisibility;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class ObjectStorageBucket extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        #[WithCast(EnumCast::class)]
        public readonly FilesystemType $type,
        #[WithCast(EnumCast::class)]
        public readonly FilesystemStatus $status,
        #[WithCast(EnumCast::class)]
        public readonly FilesystemVisibility $visibility,
        #[WithCast(EnumCast::class)]
        public readonly FilesystemJurisdiction $jurisdiction,
        public readonly ?string $endpoint = null,
        public readonly ?string $url = null,
        public readonly ?array $allowedOrigins = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly array $keyIds = [],
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
            'name' => $attributes['name'],
            'type' => $attributes['type'],
            'status' => $attributes['status'],
            'visibility' => $attributes['visibility'],
            'jurisdiction' => $attributes['jurisdiction'],
            'endpoint' => $attributes['endpoint'] ?? null,
            'url' => $attributes['url'] ?? null,
            'allowedOrigins' => $attributes['allowed_origins'] ?? null,
            'createdAt' => $attributes['created_at'] ?? null,
        ];

        if (isset($relationships['keys']['data'])) {
            $transformed['keyIds'] = array_column($relationships['keys']['data'], 'id');
        }

        return self::from($transformed);
    }
}
