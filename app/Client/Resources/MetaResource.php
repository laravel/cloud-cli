<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\ListIpAddressesRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
use App\Dto\Organization;
use App\Dto\Region;

class MetaResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function organization(): Organization
    {
        $request = new GetOrganizationRequest;

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function regions(): array
    {
        $response = $this->connector->send(new ListRegionsRequest);

        $responseData = $response->json();

        return collect($responseData['data'] ?? [])->map(fn ($item) => Region::createFromResponse(['data' => $item, 'included' => $responseData['included'] ?? []]))->toArray();
    }

    public function ipAddresses(): array
    {
        $response = $this->connector->send(new ListIpAddressesRequest);

        return $response->json() ?? [];
    }
}
