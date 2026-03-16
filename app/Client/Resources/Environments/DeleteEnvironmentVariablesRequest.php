<?php

namespace App\Client\Resources\Environments;

use App\Client\Requests\DeleteEnvironmentVariablesRequestData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class DeleteEnvironmentVariablesRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::DELETE;

    public function __construct(
        protected DeleteEnvironmentVariablesRequestData $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->data->environmentId}/variables";
    }

    protected function defaultBody(): array
    {
        return $this->data->toRequestData();
    }
}
