<?php

namespace App\Client\Resources\DatabaseClusters;

use App\Dto\DatabaseCluster;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class StartDatabaseClusterRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(
        protected string $clusterId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/databases/clusters/{$this->clusterId}/start";
    }

    public function createDtoFromResponse(Response $response): DatabaseCluster
    {
        return DatabaseCluster::createFromResponse($response->json());
    }
}
