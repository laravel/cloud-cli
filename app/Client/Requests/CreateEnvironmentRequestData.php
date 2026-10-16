<?php

namespace App\Client\Requests;

class CreateEnvironmentRequestData extends RequestData
{
    public function __construct(
        public readonly string $applicationId,
        public readonly string $name,
        public readonly string $branch,
        public readonly ?string $buildCommand = null,
        public readonly ?string $clusterId = null,
        public readonly ?string $edgeNetworkId = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'name' => $this->name,
            'branch' => $this->branch,
            'build_command' => $this->buildCommand,
            'cluster_id' => $this->clusterId,
            'edge_network_id' => $this->edgeNetworkId,
        ]);
    }
}
