<?php

namespace App\Client\Requests;

class CreateInstanceRequestData extends RequestData
{
    public function __construct(
        public readonly string $environmentId,
        public readonly string $name,
        public readonly string $type,
        public readonly string $size,
        public readonly string $scalingType,
        public readonly int $minReplicas,
        public readonly int $maxReplicas,
        public readonly ?bool $usesScheduler = null,
        public readonly ?int $scalingCpuThresholdPercentage = null,
        public readonly ?int $scalingMemoryThresholdPercentage = null,
        public readonly ?int $visibilityTimeout = null,
        public readonly ?int $pollingInterval = null,
        public readonly ?int $shutdownTimeout = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'name' => $this->name,
            'type' => $this->type,
            'size' => $this->size,
            'scaling_type' => $this->scalingType,
            'min_replicas' => $this->minReplicas,
            'max_replicas' => $this->maxReplicas,
            'uses_scheduler' => $this->usesScheduler,
            'scaling_cpu_threshold_percentage' => $this->scalingCpuThresholdPercentage,
            'scaling_memory_threshold_percentage' => $this->scalingMemoryThresholdPercentage,
            'visibility_timeout' => $this->visibilityTimeout,
            'polling_interval' => $this->pollingInterval,
            'shutdown_timeout' => $this->shutdownTimeout,
        ]);
    }
}
