<?php

namespace App\Client\Requests;

use App\Enums\InstanceScalingType;
use App\Enums\InstanceType;

class CreateInstanceRequestData extends RequestData
{
    public function __construct(
        public readonly string $environmentId,
        public readonly string $name,
        public readonly InstanceType $type,
        public readonly string $size,
        public readonly InstanceScalingType $scalingType,
        public readonly int $minReplicas,
        public readonly int $maxReplicas,
        public readonly ?bool $usesScheduler = null,
        public readonly ?int $scalingCpuThresholdPercentage = null,
        public readonly ?int $scalingMemoryThresholdPercentage = null,
        public readonly ?int $visibilityTimeout = null,
        public readonly ?int $pollingInterval = null,
        public readonly ?int $shutdownTimeout = null,
        public readonly ?array $backgroundProcesses = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'name' => $this->name,
            'type' => $this->type->value,
            'size' => $this->size,
            'scaling_type' => $this->scalingType->value,
            'min_replicas' => $this->minReplicas,
            'max_replicas' => $this->maxReplicas,
            'uses_scheduler' => $this->usesScheduler,
            'scaling_cpu_threshold_percentage' => $this->scalingCpuThresholdPercentage,
            'scaling_memory_threshold_percentage' => $this->scalingMemoryThresholdPercentage,
            'visibility_timeout' => $this->visibilityTimeout,
            'polling_interval' => $this->pollingInterval,
            'shutdown_timeout' => $this->shutdownTimeout,
            'background_processes' => $this->backgroundProcesses,
        ]);
    }
}
