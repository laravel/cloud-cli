<?php

namespace App\Client\Resources\Domains;

use App\Dto\Domain;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class UpdateDomainRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    public function __construct(
        protected string $domainId,
        protected array $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/domains/{$this->domainId}";
    }

    protected function defaultBody(): array
    {
        return $this->data;
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return Domain::createFromResponse($response->json());
    }
}
