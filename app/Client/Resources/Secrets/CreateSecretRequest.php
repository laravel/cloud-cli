<?php

namespace App\Client\Resources\Secrets;

use App\Client\Requests\CreateSecretRequestData;
use App\Dto\Secret;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class CreateSecretRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected CreateSecretRequestData $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return '/secrets';
    }

    protected function defaultBody(): array
    {
        return $this->data->toRequestData();
    }

    public function createDtoFromResponse(Response $response): Secret
    {
        return Secret::createFromResponse($response->json());
    }
}
