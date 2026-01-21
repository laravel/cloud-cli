<?php

namespace App\Dto;

use Carbon\CarbonImmutable;

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
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?CarbonImmutable $updatedAt = null,
        public readonly array $schemas = [],
    ) {
        //
    }

    public static function fromApiResponse(array $response, ?array $item = null): self
    {
        $data = $item ?? $response['data'] ?? [];
        $included = $response['included'] ?? [];
        $attributes = $data['attributes'] ?? [];

        return new self(
            id: $data['id'],
            name: $attributes['name'],
            type: $attributes['type'],
            status: $attributes['status'],
            region: $attributes['region'],
            config: $attributes['config'] ?? [],
            connection: $attributes['connection'] ?? [],
            createdAt: isset($attributes['created_at']) ? CarbonImmutable::parse($attributes['created_at']) : null,
            updatedAt: isset($attributes['updated_at']) ? CarbonImmutable::parse($attributes['updated_at']) : null,
            schemas: collect($included)
                ->filter(fn ($item) => $item['type'] === 'databaseSchemas')
                ->map(fn ($item) => Database::fromApiResponse(['data' => $item]))
                ->values()
                ->toArray(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
            'region' => $this->region,
        ];
    }
}
