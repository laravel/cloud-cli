<?php

namespace App\Client\Resources\DatabaseClusters;

use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\DatabaseCluster;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListDatabaseClustersRequest extends Request implements Paginatable
{
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function __construct(
        protected ?string $type = null,
        protected ?string $region = null,
        protected ?string $status = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return '/databases/clusters';
    }

    protected function defaultQuery(): array
    {
        return array_merge(
            $this->includeQuery(),
            array_filter([
                'filter[type]' => $this->type,
                'filter[region]' => $this->region,
                'filter[status]' => $this->status,
            ]),
        );
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return array_map(fn ($cluster) => DatabaseCluster::createFromResponse([
            'data' => $cluster,
            'included' => $response->json('included', []),
        ]), $response->json('data'));
    }
}
