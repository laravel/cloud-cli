<?php

namespace App\Dto;

use Carbon\CarbonImmutable;

class EnvironmentInstance
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
        public readonly ?string $environmentId = null,
        public readonly array $backgroundProcessIds = [],
    ) {
        //
    }

    public static function fromApiResponse(array $data): self
    {
        $attributes = $data['attributes'] ?? [];
        $relationships = $data['relationships'] ?? [];

        dump($data);

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
            environmentId: $relationships['environment']['data']['id'] ?? null,
            backgroundProcessIds: array_column($relationships['backgroundProcesses']['data'] ?? [], 'id'),
        );
    }
}
