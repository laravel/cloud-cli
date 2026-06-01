<?php

namespace App\Client\Requests;

class UpdateInstanceRequestData extends RequestData
{
    public function __construct(
        public readonly string $instanceId,
        public readonly ?string $name = null,
        public readonly ?string $type = null,
        public readonly ?string $size = null,
        public readonly ?string $scalingType = null,
        public readonly ?int $minReplicas = null,
        public readonly ?int $maxReplicas = null,
        public readonly ?bool $usesScheduler = null,
        public readonly ?bool $usesOctane = null,
        public readonly ?bool $usesInertiaSsr = null,
        public readonly ?bool $usesSleepMode = null,
        public readonly ?int $sleepTimeout = null,
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
            'uses_octane' => $this->usesOctane,
            'uses_inertia_ssr' => $this->usesInertiaSsr,
            'uses_sleep_mode' => $this->usesSleepMode,
            'sleep_timeout' => $this->sleepTimeout,
            'scaling_cpu_threshold_percentage' => $this->scalingCpuThresholdPercentage,
            'scaling_memory_threshold_percentage' => $this->scalingMemoryThresholdPercentage,
            'visibility_timeout' => $this->visibilityTimeout,
            'polling_interval' => $this->pollingInterval,
            'shutdown_timeout' => $this->shutdownTimeout,
        ]);
    }
}
