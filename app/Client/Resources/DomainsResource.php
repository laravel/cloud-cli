<?php

namespace App\Client\Resources;

use App\Client\Resources\Domains\CreateDomainRequest;
use App\Client\Resources\Domains\DeleteDomainRequest;
use App\Client\Resources\Domains\GetDomainRequest;
use App\Client\Resources\Domains\ListDomainsRequest;
use App\Client\Resources\Domains\UpdateDomainRequest;
use App\Client\Resources\Domains\VerifyDomainRequest;
use App\Dto\Domain;
use Saloon\PaginationPlugin\Paginator;

class DomainsResource extends Resource
{
    public function list(string $environmentId): Paginator
    {
        $request = new ListDomainsRequest($environmentId);

        return $this->paginate($request);
    }

    public function get(string $domainId): Domain
    {
        $request = new GetDomainRequest($domainId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(string $environmentId, string $name, array $data): Domain
    {
        $request = new CreateDomainRequest(
            environmentId: $environmentId,
            name: $name,
            data: $data,
        );

        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(string $domainId, array $data): Domain
    {
        $request = new UpdateDomainRequest(
            domainId: $domainId,
            data: $data,
        );

        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $domainId): void
    {
        $this->send(new DeleteDomainRequest($domainId));
    }

    public function verify(string $domainId): void
    {
        $this->send(new VerifyDomainRequest($domainId));
    }
}
