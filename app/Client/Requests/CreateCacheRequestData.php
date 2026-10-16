<?php

namespace App\Client\Requests;

class CreateCacheRequestData extends RequestData
{
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly string $region,
        public readonly string $size,
        public readonly bool $autoUpgradeEnabled,
        public readonly bool $isPublic,
        public readonly ?string $evictionPolicy = null,
        public readonly ?bool $usesHibernation = null,
        public readonly ?int $hibernationTimeout = null,
        public readonly ?string $clusterId = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'region' => $this->region,
            'size' => $this->size,
            'auto_upgrade_enabled' => $this->autoUpgradeEnabled,
            'is_public' => $this->isPublic,
            'eviction_policy' => $this->evictionPolicy,
            ...$this->filter([
                'uses_hibernation' => $this->usesHibernation,
                'hibernation_timeout' => $this->hibernationTimeout,
                'cluster_id' => $this->clusterId,
            ]),
        ];
    }
}
