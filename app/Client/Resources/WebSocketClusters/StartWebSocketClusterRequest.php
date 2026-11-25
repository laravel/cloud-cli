<?php

namespace App\Client\Resources\WebSocketClusters;

use App\Dto\WebsocketCluster;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class StartWebSocketClusterRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(
        protected string $clusterId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/websocket-servers/{$this->clusterId}/start";
    }

    public function createDtoFromResponse(Response $response): WebsocketCluster
    {
        return WebsocketCluster::createFromResponse($response->json());
    }
}
