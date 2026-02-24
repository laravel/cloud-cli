<?php

namespace App\Client\Resources\Applications;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteApplicationRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected string $applicationId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/applications/{$this->applicationId}";
    }
}
