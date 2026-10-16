<?php

namespace App\Client\Resources\DedicatedClusters;

use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\DedicatedCluster;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListDedicatedClustersRequest extends Request implements Paginatable
{
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function __construct(
        protected ?string $region = null,
        protected ?string $type = null,
        protected ?string $status = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return '/dedicated-clusters';
    }

    protected function defaultQuery(): array
    {
        return array_merge(
            $this->includeQuery(),
            array_filter([
                'filter[region]' => $this->region,
                'filter[type]' => $this->type,
                'filter[status]' => $this->status,
            ]),
        );
    }

    public function createDtoFromResponse(Response $response): array
    {
        $data = $response->json('data') ?? [];

        return array_map(fn (array $item) => DedicatedCluster::createFromResponse(['data' => $item]), $data);
    }
}
