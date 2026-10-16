<?php

namespace App\Client\Requests;

class CreateDatabaseClusterRequestData extends RequestData
{
    public function __construct(
        public readonly string $type,
        public readonly string $version,
        public readonly string $name,
        public readonly string $region,
        public readonly array $config,
        public readonly ?string $clusterId = null,
        public readonly ?bool $createDefaultDatabase = null,
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
            'create_default_database' => $this->createDefaultDatabase,
        ]);
    }
}
