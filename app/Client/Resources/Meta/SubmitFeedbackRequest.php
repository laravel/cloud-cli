<?php

namespace App\Client\Resources\Meta;

use App\Client\Requests\SubmitFeedbackRequestData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class SubmitFeedbackRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected SubmitFeedbackRequestData $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return '/feedback';
    }

    protected function defaultBody(): array
    {
        return $this->data->toRequestData();
    }
}
