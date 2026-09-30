<?php

namespace App\Client\Resources\DatabaseClusters;

use App\Dto\DatabaseClusterMetrics;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetDatabaseClusterMetricsRequest extends Request
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
        return "/databases/clusters/{$this->clusterId}/metrics";
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'period' => $this->period,
        ]);
    }

    public function createDtoFromResponse(Response $response): DatabaseClusterMetrics
    {
        return DatabaseClusterMetrics::createFromResponse($response->json());
    }
}
