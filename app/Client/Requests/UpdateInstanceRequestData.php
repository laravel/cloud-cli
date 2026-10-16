<?php

namespace App\Client\Requests;

class UpdateInstanceRequestData extends RequestData
{
    /**
     * @param  array{startup?: array<string, int|string|null>|null, readiness?: array<string, int|string|null>|null, liveness?: array<string, int|string|null>|null}|null  $probes
     */
    public function __construct(
        public readonly string $instanceId,
        public readonly ?string $name = null,
        public readonly ?string $size = null,
        public readonly ?string $scalingType = null,
        public readonly ?int $minReplicas = null,
        public readonly ?int $maxReplicas = null,
        public readonly ?bool $usesScheduler = null,
        public readonly ?bool $usesOctane = null,
        public readonly ?bool $usesInertiaSsr = null,
        public readonly ?int $hibernationTimeout = null,
        // Cloud reads a null hibernation_timeout as "turn hibernation off" and a missing one as "leave it alone".
        public readonly bool $disableHibernation = false,
        public readonly ?bool $sleepWithApp = null,
        public readonly ?int $scalingCpuThresholdPercentage = null,
        public readonly ?int $scalingMemoryThresholdPercentage = null,
        public readonly ?int $visibilityTimeout = null,
        public readonly ?int $pollingInterval = null,
        public readonly ?int $shutdownTimeout = null,
        public readonly ?array $probes = null,
        // Same as hibernation: a null probes field removes the custom checks, a missing one keeps them.
        public readonly bool $removeProbes = false,
    ) {
        //
    }

    public function toRequestData(): array
    {
        $data = $this->filter([
            'name' => $this->name,
            'size' => $this->size,
            'scaling_type' => $this->scalingType,
            'min_replicas' => $this->minReplicas,
            'max_replicas' => $this->maxReplicas,
            'uses_scheduler' => $this->usesScheduler,
            'uses_octane' => $this->usesOctane,
            'uses_inertia_ssr' => $this->usesInertiaSsr,
            'hibernation_timeout' => $this->hibernationTimeout,
            'sleep_with_app' => $this->sleepWithApp,
            'scaling_cpu_threshold_percentage' => $this->scalingCpuThresholdPercentage,
            'scaling_memory_threshold_percentage' => $this->scalingMemoryThresholdPercentage,
            'visibility_timeout' => $this->visibilityTimeout,
            'polling_interval' => $this->pollingInterval,
            'shutdown_timeout' => $this->shutdownTimeout,
            'probes' => $this->probes,
        ]);

        if ($this->disableHibernation) {
            $data['hibernation_timeout'] = null;
        }

        if ($this->removeProbes) {
            $data['probes'] = null;
        }

        return $data;
    }
}
