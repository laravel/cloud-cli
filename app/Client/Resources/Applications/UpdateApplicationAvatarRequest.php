<?php

namespace App\Client\Resources\Applications;

use App\Client\Requests\UpdateApplicationAvatarRequestData;
use App\Dto\Application;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasMultipartBody;

class UpdateApplicationAvatarRequest extends Request implements HasBody
{
    use HasMultipartBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected UpdateApplicationAvatarRequestData $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/applications/{$this->data->applicationId}/avatar";
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
