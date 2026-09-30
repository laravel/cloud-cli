<?php

namespace App\Client\Resources\WebSocketApplications;

use App\Dto\WebsocketMetrics;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetWebSocketApplicationMetricsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $applicationId,
        protected ?string $period = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/websocket-applications/{$this->applicationId}/metrics";
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
