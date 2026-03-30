<?php

namespace App\Client\Resources\Applications;

use App\Client\Requests\UpdateApplicationRequestData;
use App\Dto\Application;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class UpdateApplicationRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    public function __construct(
        protected UpdateApplicationRequestData $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/applications/{$this->data->applicationId}";
    }

    protected function defaultBody(): array
    {
        return $this->data->toRequestData();
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return Application::createFromResponse($response->json());
    }
}
