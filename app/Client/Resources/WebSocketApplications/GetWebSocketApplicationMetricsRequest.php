<?php

namespace App\Client\Resources\WebSocketApplications;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetWebSocketApplicationMetricsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $applicationId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/websocket-applications/{$this->applicationId}/metrics";
    }

    public function createDtoFromResponse(Response $response): array
    {
        return $response->json('data') ?? $response->json();
    }
}
