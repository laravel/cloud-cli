<?php

namespace App\Client\Resources\Environments;

use App\Client\Requests\UpdateEnvironmentPrimaryDomainRequestData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class UpdatePrimaryDomainRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        protected UpdateEnvironmentPrimaryDomainRequestData $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->data->environmentId}/primary-domain";
    }

    protected function defaultBody(): array
    {
        return $this->data->toRequestData();
    }
}
