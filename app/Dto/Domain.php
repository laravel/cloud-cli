<?php

namespace App\Dto;

use Carbon\CarbonImmutable;

class Domain extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $domain,
        public readonly string $status,
        public readonly bool $isPrimary,
        public readonly ?string $verificationStatus = null,
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?CarbonImmutable $updatedAt = null,
        public readonly ?string $environmentId = null,
    ) {
        //
    }

    public static function fromApiResponse(array $response, ?array $item = null): self
    {
        $data = $item ?? $response['data'] ?? [];
        $included = $response['included'] ?? [];

        $attributes = $data['attributes'] ?? [];
        $relationships = $data['relationships'] ?? [];

        return new self(
            id: $data['id'],
            domain: $attributes['domain'] ?? '',
            status: $attributes['status'] ?? '',
            isPrimary: $attributes['is_primary'] ?? false,
            verificationStatus: $attributes['verification_status'] ?? null,
            createdAt: isset($attributes['created_at']) ? CarbonImmutable::parse($attributes['created_at']) : null,
            updatedAt: isset($attributes['updated_at']) ? CarbonImmutable::parse($attributes['updated_at']) : null,
            environmentId: $relationships['environment']['data']['id'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'status' => $this->status,
            'is_primary' => $this->isPrimary,
            'verification_status' => $this->verificationStatus,
            'created_at' => $this->createdAt?->toIso8601String(),
            'updated_at' => $this->updatedAt?->toIso8601String(),
            'environment_id' => $this->environmentId,
        ];
    }
}
