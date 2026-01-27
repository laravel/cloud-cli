<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\DedicatedClusters\ListDedicatedClustersRequest;

class DedicatedClustersResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(): array
    {
        $response = $this->connector->send(new ListDedicatedClustersRequest);

        return $response->json()['data'] ?? [];
    }
}
