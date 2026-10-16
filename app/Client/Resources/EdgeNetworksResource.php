<?php

namespace App\Client\Resources;

use App\Client\Resources\EdgeNetworks\ListEdgeNetworksRequest;
use Saloon\PaginationPlugin\Paginator;

class EdgeNetworksResource extends Resource
{
    public function list(?string $name = null, ?string $domain = null, ?string $status = null): Paginator
    {
        return $this->paginate(new ListEdgeNetworksRequest($name, $domain, $status));
    }
}
