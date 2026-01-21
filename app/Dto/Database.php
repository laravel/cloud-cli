<?php

namespace App\Dto;

use Carbon\CarbonImmutable;

class Database extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?CarbonImmutable $createdAt = null,
    ) {
        //
    }

    public static function fromApiResponse(array $response, ?array $item = null): self
    {
        $data = $item ?? $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];

        return new self(
            id: $data['id'],
            name: $attributes['name'],
            createdAt: isset($attributes['created_at']) ? CarbonImmutable::parse($attributes['created_at']) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->createdAt?->toIso8601String(),
        ];
    }
}
