<?php

namespace App\Client\Requests;

class CreateDatabaseClusterRequestData extends RequestData
{
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly string $region,
        public readonly array $config,
        public readonly ?string $version = null,
        public readonly ?int $clusterId = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'type' => $this->type,
            'version' => $this->version,
            'name' => $this->name,
            'region' => $this->region,
            'config' => $this->config,
            'cluster_id' => $this->clusterId,
        ]);
    }
}
