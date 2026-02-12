<?php

namespace App\Client\Resources\WebSocketApplications;

use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\WebsocketApplication;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetWebSocketApplicationRequest extends Request
{
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $applicationId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/websocket-applications/{$this->applicationId}";
    }

    public function createDtoFromResponse(Response $response): WebsocketApplication
    {
        return WebsocketApplication::createFromResponse($response->json());
    }
}
