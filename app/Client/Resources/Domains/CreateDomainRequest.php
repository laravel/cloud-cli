<?php

namespace App\Client\Resources\Domains;

use App\Client\Requests\CreateDomainRequestData;
use App\Dto\Domain;
use JsonException;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class CreateDomainRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected CreateDomainRequestData $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->data->environmentId}/domains";
    }

    protected function defaultBody(): array
    {
        return $this->data->toRequestData();
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        try {
            return Domain::createFromResponse($response->json());
        } catch (JsonException) {
            // The API may return a non-JSON response (e.g., empty body or
            // the domain ID in the Location header). Parse what we can.
            return Domain::from([
                'id' => $response->header('X-Resource-Id') ?? 'unknown',
                'name' => $this->data->name,
                'type' => 'root',
                'hostnameStatus' => 'pending',
                'sslStatus' => 'pending',
                'originStatus' => 'pending',
            ]);
        }
    }
}
