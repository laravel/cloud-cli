<?php

namespace App\Client\Resources\DatabaseClusters;

use App\Dto\DatabaseCluster;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListDatabaseClustersRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(
        protected ?string $include = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return '/databases/clusters';
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'include' => $this->include,
        ]);
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return array_map(fn ($cluster) => DatabaseCluster::createFromResponse([
            'data' => $cluster,
            'included' => $response->json('included', []),
        ]), $response->json('data'));
    }
}
