<?php

namespace App\Client\Resources\EdgeNetworks;

use App\Dto\EdgeNetwork;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListEdgeNetworksRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(
        protected ?string $name = null,
        protected ?string $domain = null,
        protected ?string $status = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return '/edge-networks';
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'filter[name]' => $this->name,
            'filter[domain]' => $this->domain,
            'filter[status]' => $this->status,
        ]);
    }

    public function createDtoFromResponse(Response $response): array
    {
        return array_map(
            fn (array $item) => EdgeNetwork::createFromResponse(['data' => $item]),
            $response->json('data') ?? [],
        );
    }
}
