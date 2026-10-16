<?php

namespace App\Client\Requests;

class UpdateCacheRequestData extends RequestData
{
    public function __construct(
        public readonly string $cacheId,
        public readonly ?string $name = null,
        public readonly ?string $size = null,
        public readonly ?bool $autoUpgradeEnabled = null,
        public readonly ?bool $isPublic = null,
        public readonly ?string $evictionPolicy = null,
        public readonly ?bool $usesHibernation = null,
        public readonly ?int $hibernationTimeout = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'name' => $this->name,
            'size' => $this->size,
            'auto_upgrade_enabled' => $this->autoUpgradeEnabled,
            'is_public' => $this->isPublic,
            'eviction_policy' => $this->evictionPolicy,
            'uses_hibernation' => $this->usesHibernation,
            'hibernation_timeout' => $this->hibernationTimeout,
        ]);
    }
}
