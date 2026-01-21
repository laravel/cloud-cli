<?php

namespace App\Dto;

use Carbon\CarbonImmutable;

class EnvironmentInstance extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type,
        public readonly string $size,
        public readonly string $scalingType,
        public readonly int $minReplicas,
        public readonly int $maxReplicas,
        public readonly bool $usesScheduler,
        public readonly ?int $scalingCpuThresholdPercentage = null,
        public readonly ?int $scalingMemoryThresholdPercentage = null,
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?CarbonImmutable $updatedAt = null,
        public readonly ?string $environmentId = null,
        public readonly array $backgroundProcessIds = [],
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
            name: $attributes['name'],
            type: $attributes['type'],
            size: $attributes['size'],
            scalingType: $attributes['scaling_type'],
            minReplicas: $attributes['min_replicas'],
            maxReplicas: $attributes['max_replicas'],
            usesScheduler: $attributes['uses_scheduler'],
            scalingCpuThresholdPercentage: $attributes['scaling_cpu_threshold_percentage'],
            scalingMemoryThresholdPercentage: $attributes['scaling_memory_threshold_percentage'],
            createdAt: isset($attributes['created_at']) ? CarbonImmutable::parse($attributes['created_at']) : null,
            updatedAt: isset($attributes['updated_at']) ? CarbonImmutable::parse($attributes['updated_at']) : null,
            environmentId: $relationships['environment']['data']['id'] ?? null,
            backgroundProcessIds: array_column($relationships['backgroundProcesses']['data'] ?? [], 'id'),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'size' => $this->size,
            'scaling_type' => $this->scalingType,
            'min_replicas' => $this->minReplicas,
            'max_replicas' => $this->maxReplicas,
            'uses_scheduler' => $this->usesScheduler,
            'scaling_cpu_threshold_percentage' => $this->scalingCpuThresholdPercentage,
            'scaling_memory_threshold_percentage' => $this->scalingMemoryThresholdPercentage,
            'created_at' => $this->createdAt?->toIso8601String(),
            'updated_at' => $this->updatedAt?->toIso8601String(),
            'environment_id' => $this->environmentId,
            'background_process_ids' => $this->backgroundProcessIds,
        ];
    }
}
