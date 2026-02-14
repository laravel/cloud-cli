<?php

namespace App\Client\Resources\WebSocketApplications;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteWebSocketApplicationRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected string $applicationId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/websocket-applications/{$this->applicationId}";
    }
}
