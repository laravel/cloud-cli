<?php

namespace App\Client\Resources;

use App\Client\Resources\DedicatedClusters\ListDedicatedClustersRequest;
use Saloon\PaginationPlugin\Paginator;

class DedicatedClustersResource extends Resource
{
    public function list(?string $region = null, ?string $type = null, ?string $status = null): Paginator
    {
        $request = new ListDedicatedClustersRequest(
            region: $region,
            type: $type,
            status: $status,
        );

        return $this->paginate($request);
    }
}
