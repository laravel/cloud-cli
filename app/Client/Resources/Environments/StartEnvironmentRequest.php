<?php

namespace App\Client\Resources\Environments;

use App\Client\Requests\StartEnvironmentRequestData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class StartEnvironmentRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected StartEnvironmentRequestData $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->data->environmentId}/start";
    }

    protected function defaultBody(): array
    {
        return $this->data->toRequestData();
    }
}
