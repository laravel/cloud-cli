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
        public readonly ?Environment $environment = null,
        public readonly array $backgroundProcessIds = [],
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];
        $relationships = $data['relationships'] ?? [];
        $included = $response['included'] ?? [];

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
            'environment' => ($relationships['environment']['data'] ?? null) ? Environment::createFromResponse(['data' => collect($included)->firstWhere('type', 'environments')]) : null,
        ];

        if (isset($relationships['backgroundProcesses']['data'])) {
            $transformed['backgroundProcessIds'] = array_column($relationships['backgroundProcesses']['data'], 'id');
        }

        return self::from($transformed);
    }
}
