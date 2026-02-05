<?php

namespace App\Client\Resources\WebSocketApplications;

use App\Dto\WebsocketApplication;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListWebSocketApplicationsRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $clusterId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/websocket-servers/{$this->clusterId}/applications";
    }

    public function createDtoFromResponse(Response $response): array
    {
        $data = $response->json('data') ?? [];
        $included = $response->json('included') ?? [];

        return array_map(fn (array $item) => WebsocketApplication::createFromResponse(['data' => $item, 'included' => $included]), $data);
    }
}
