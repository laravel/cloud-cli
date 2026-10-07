<?php

namespace App\Client\Resources\Resend;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DetachResendRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected string $environmentId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->environmentId}/resend";
    }
}
