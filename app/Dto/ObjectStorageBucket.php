<?php

namespace App\Dto;

use App\Enums\FilesystemStatus;
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
        // Kept as strings so a type, jurisdiction, or location hint the CLI doesn't know yet still reads.
        public readonly string $type,
        #[WithCast(EnumCast::class)]
        public readonly FilesystemStatus $status,
        #[WithCast(EnumCast::class)]
        public readonly FilesystemVisibility $visibility,
        public readonly string $jurisdiction,
        public readonly ?string $locationHint = null,
        public readonly ?string $endpoint = null,
        public readonly ?string $url = null,
        public readonly ?array $allowedOrigins = null,
        public readonly ?array $corsSettings = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly array $keyIds = [],
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
            'name' => $attributes['name'],
            'type' => $attributes['type'],
            'status' => $attributes['status'],
            'visibility' => $attributes['visibility'],
            'jurisdiction' => $attributes['jurisdiction'],
            'locationHint' => $attributes['location_hint'] ?? null,
            'endpoint' => $attributes['endpoint'] ?? null,
            'url' => $attributes['url'] ?? null,
            'allowedOrigins' => $attributes['allowed_origins'] ?? null,
            'corsSettings' => $attributes['cors_settings'] ?? null,
            'createdAt' => $attributes['created_at'] ?? null,
        ];

        if (isset($relationships['keys']['data'])) {
            $transformed['keyIds'] = array_column($relationships['keys']['data'], 'id');
        }

        return self::from($transformed);
    }
}
