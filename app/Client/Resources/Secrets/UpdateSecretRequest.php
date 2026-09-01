<?php

namespace App\Client\Resources\Secrets;

use App\Client\Requests\UpdateSecretRequestData;
use App\Dto\Secret;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class UpdateSecretRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    public function __construct(
        protected UpdateSecretRequestData $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/secrets/{$this->data->secretId}";
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
