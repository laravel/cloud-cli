<?php

namespace App\Client\Resources\DatabaseClusters;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetDatabaseClusterMetricsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $clusterId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/databases/clusters/{$this->clusterId}/metrics";
    }

    public function createDtoFromResponse(Response $response): array
    {
        return $response->json('data') ?? $response->json();
    }
}
