<?php

namespace App\Client\Resources\DatabaseClusters;

use App\Dto\DatabaseCluster;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class CreateDatabaseClusterRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected string $type,
        protected string $name,
        protected string $region,
        protected array $clusterConfig,
        protected ?int $clusterId = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return '/databases/clusters';
    }

    protected function defaultBody(): array
    {
        return array_filter([
            'type' => $this->type,
            'name' => $this->name,
            'region' => $this->region,
            'config' => $this->clusterConfig,
            'cluster_id' => $this->clusterId,
        ]);
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return DatabaseCluster::createFromResponse($response->json());
    }
}
