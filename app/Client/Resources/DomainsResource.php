<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\Domains\CreateDomainRequest;
use App\Client\Resources\Domains\DeleteDomainRequest;
use App\Client\Resources\Domains\GetDomainRequest;
use App\Client\Resources\Domains\ListDomainsRequest;
use App\Client\Resources\Domains\UpdateDomainRequest;
use App\Client\Resources\Domains\VerifyDomainRequest;
use App\Dto\Domain;
use Saloon\PaginationPlugin\Paginator;

class DomainsResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(string $environmentId): Paginator
    {
        $request = new ListDomainsRequest($environmentId);

        return $this->connector->paginate($request)->transform(function ($response) {
            $responseData = $response->json();

            return collect($responseData['data'] ?? [])->map(fn ($item) => Domain::fromJsonApi(['data' => $item, 'included' => $responseData['included'] ?? []]))->toArray();
        });
    }

    public function get(string $domainId): Domain
    {
        $response = $this->connector->send(new GetDomainRequest($domainId));

        return Domain::fromJsonApi($response->json());
    }

    public function create(string $environmentId, string $domain): Domain
    {
        $response = $this->connector->send(new CreateDomainRequest(
            environmentId: $environmentId,
            domain: $domain,
        ));

        return Domain::fromJsonApi($response->json());
    }

    public function update(string $domainId, array $data): Domain
    {
        $response = $this->connector->send(new UpdateDomainRequest(
            domainId: $domainId,
            data: $data,
        ));

        return Domain::fromJsonApi($response->json());
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
