<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\Domains\CreateDomainRequest;
use App\Client\Resources\Domains\DeleteDomainRequest;
use App\Client\Resources\Domains\GetDomainRequest;
use App\Client\Resources\Domains\ListDomainsRequest;
use App\Client\Resources\Domains\UpdateDomainRequest;
use App\Client\Resources\Domains\VerifyDomainRequest;
use App\Client\ResponseMapper;
use App\Dto\Domain;
use App\Dto\Paginated;

class DomainsResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(string $environmentId): Paginated
    {
        $response = $this->connector->send(new ListDomainsRequest($environmentId));

        return ResponseMapper::mapPaginated($response->json(), fn ($response, $item) => ResponseMapper::mapDomain($response, $item));
    }

    public function get(string $domainId): Domain
    {
        $response = $this->connector->send(new GetDomainRequest($domainId));

        return ResponseMapper::mapDomain($response->json());
    }

    public function create(string $environmentId, string $domain): Domain
    {
        $response = $this->connector->send(new CreateDomainRequest(
            environmentId: $environmentId,
            domain: $domain,
        ));

        return ResponseMapper::mapDomain($response->json());
    }

    public function update(string $domainId, array $data): Domain
    {
        $response = $this->connector->send(new UpdateDomainRequest(
            domainId: $domainId,
            data: $data,
        ));

        return ResponseMapper::mapDomain($response->json());
    }

    public function delete(string $domainId): void
    {
        $this->connector->send(new DeleteDomainRequest($domainId));
    }

    public function verify(string $domainId): void
    {
        $this->connector->send(new VerifyDomainRequest($domainId));
    }
}
