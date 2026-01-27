<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class DatabaseCluster extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type,
        public readonly string $status,
        public readonly string $region,
        public readonly array $config,
        public readonly array $connection,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $updatedAt = null,
        #[DataCollectionOf(Database::class)]
        public readonly array $schemas = [],
    ) {
        //
    }

    public static function fromJsonApi(array $response): self
    {
        $data = $response['data'] ?? [];
        $included = $response['included'] ?? [];
        $attributes = $data['attributes'] ?? [];

        $transformed = [
            'id' => $data['id'],
            'name' => $attributes['name'],
            'type' => $attributes['type'],
            'status' => $attributes['status'],
            'region' => $attributes['region'],
            'config' => $attributes['config'] ?? [],
            'connection' => $attributes['connection'] ?? [],
            'createdAt' => $attributes['created_at'] ?? null,
            'updatedAt' => $attributes['updated_at'] ?? null,
        ];

        $schemaData = collect($included)
            ->filter(fn ($item) => $item['type'] === 'databaseSchemas')
            ->values()
            ->toArray();
        $transformed['schemas'] = collect($schemaData)->map(fn ($item) => Database::fromJsonApi(['data' => $item, 'included' => $included])->toArray())->toArray();

        return self::from($transformed);
    }
}
