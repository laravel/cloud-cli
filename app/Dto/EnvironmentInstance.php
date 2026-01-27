<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

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
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $updatedAt = null,
        public readonly ?string $environmentId = null,
        public readonly array $backgroundProcessIds = [],
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
            'size' => $attributes['size'],
            'scalingType' => $attributes['scaling_type'],
            'minReplicas' => $attributes['min_replicas'],
            'maxReplicas' => $attributes['max_replicas'],
            'usesScheduler' => $attributes['uses_scheduler'],
            'scalingCpuThresholdPercentage' => $attributes['scaling_cpu_threshold_percentage'] ?? null,
            'scalingMemoryThresholdPercentage' => $attributes['scaling_memory_threshold_percentage'] ?? null,
            'createdAt' => $attributes['created_at'] ?? null,
            'updatedAt' => $attributes['updated_at'] ?? null,
        ];

        if (isset($relationships['environment']['data']['id'])) {
            $transformed['environmentId'] = $relationships['environment']['data']['id'];
        }

        if (isset($relationships['backgroundProcesses']['data'])) {
            $transformed['backgroundProcessIds'] = array_column($relationships['backgroundProcesses']['data'], 'id');
        }

        return self::from($transformed);
    }
}
