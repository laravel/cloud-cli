<?php

namespace App\Client\Resources\WebSocketClusters;

use App\Dto\WebsocketMetrics;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetWebSocketClusterMetricsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $clusterId,
        protected ?string $period = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/websocket-servers/{$this->clusterId}/metrics";
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'period' => $this->period,
        ]);
    }

    public function createDtoFromResponse(Response $response): WebsocketMetrics
    {
        return WebsocketMetrics::createFromResponse($response->json());
    }
}
