<?php

namespace App\Client\Resources\Environments;

use App\Client\Requests\UpdateVanityDomainRequestData;
use App\Dto\Environment;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class UpdateVanityDomainRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        protected UpdateVanityDomainRequestData $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->data->environmentId}/vanity-domain";
    }

    protected function defaultBody(): array
    {
        return $this->data->toRequestData();
    }

    public function createDtoFromResponse(Response $response): Environment
    {
        return Environment::createFromResponse($response->json());
    }
}
