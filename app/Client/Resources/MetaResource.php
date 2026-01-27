<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\ListIpAddressesRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
use App\Client\ResponseMapper;
use App\Dto\Organization;

class MetaResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function organization(): Organization
    {
        $response = $this->connector->send(new GetOrganizationRequest);

        return ResponseMapper::mapOrganization($response->json());
    }

    public function regions(): array
    {
        $response = $this->connector->send(new ListRegionsRequest);

        return collect($response->json()['data'] ?? [])->map(fn ($item) => ResponseMapper::mapRegion($response->json(), $item))->toArray();
    }

    public function ipAddresses(): array
    {
        $response = $this->connector->send(new ListIpAddressesRequest);

        return $response->json() ?? [];
    }
}
