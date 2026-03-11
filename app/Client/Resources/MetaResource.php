<?php

namespace App\Client\Resources;

use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\ListIpAddressesRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
use App\Dto\Organization;

class MetaResource extends Resource
{
    public function organization(): Organization
    {
        $request = new GetOrganizationRequest;
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function regions(): array
    {
        $request = new ListRegionsRequest;
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function ipAddresses(): array
    {
        $response = $this->send(new ListIpAddressesRequest);

        return $response->json();
    }
}
