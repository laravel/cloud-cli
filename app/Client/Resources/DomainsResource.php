<?php

namespace App\Client\Resources;

use App\Client\Requests\CreateDomainRequestData;
use App\Client\Requests\UpdateDomainRequestData;
use App\Client\Requests\VerifyDomainRequestData;
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
    public function list(string $environmentId, ?string $name = null, ?string $hostnameStatus = null, ?string $sslStatus = null, ?string $originStatus = null): Paginator
    {
        $request = new ListDomainsRequest(
            environmentId: $environmentId,
            name: $name,
            hostnameStatus: $hostnameStatus,
            sslStatus: $sslStatus,
            originStatus: $originStatus,
        );

        return $this->paginate($request);
    }

    public function get(string $domainId): Domain
    {
        $request = new GetDomainRequest($domainId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(CreateDomainRequestData $data): Domain
    {
        $request = new CreateDomainRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(UpdateDomainRequestData $data): Domain
    {
        $request = new UpdateDomainRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $domainId): void
    {
        $this->send(new DeleteDomainRequest($domainId));
    }

    public function verify(string $domainId): Domain
    {
        $request = new VerifyDomainRequest(new VerifyDomainRequestData($domainId));

        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }
}
