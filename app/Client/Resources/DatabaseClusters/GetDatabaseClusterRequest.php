<?php

namespace App\Client\Resources\DatabaseClusters;

use App\Dto\DatabaseCluster;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetDatabaseClusterRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $clusterId,
        protected ?string $include = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/databases/clusters/{$this->clusterId}";
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'include' => $this->include,
        ]);
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return DatabaseCluster::createFromResponse($response->json());
    }
}
