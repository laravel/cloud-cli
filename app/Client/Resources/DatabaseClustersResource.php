<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\DatabaseClusters\CreateDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\DeleteDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseTypesRequest;
use App\Client\Resources\DatabaseClusters\UpdateDatabaseClusterRequest;
use App\Dto\DatabaseCluster;
use App\Dto\DatabaseType;
use Saloon\PaginationPlugin\Paginator;

class DatabaseClustersResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(?string $include = null): Paginator
    {
        $request = new ListDatabaseClustersRequest(include: $include);

        return $this->connector->paginate($request)->transform(fn ($responseData, $item) => DatabaseCluster::fromJsonApi(['data' => $item, 'included' => $responseData['included'] ?? []]));
    }

    public function get(string $clusterId, ?string $include = null): DatabaseCluster
    {
        $response = $this->connector->send(new GetDatabaseClusterRequest(
            clusterId: $clusterId,
            include: $include,
        ));

        return DatabaseCluster::fromJsonApi($response->json());
    }

    public function create(string $type, string $name, string $region, array $config, ?int $clusterId = null): DatabaseCluster
    {
        $response = $this->connector->send(new CreateDatabaseClusterRequest(
            type: $type,
            name: $name,
            region: $region,
            config: $config,
            clusterId: $clusterId,
        ));

        return DatabaseCluster::fromJsonApi($response->json());
    }

    public function update(string $clusterId, array $data): DatabaseCluster
    {
        $response = $this->connector->send(new UpdateDatabaseClusterRequest(
            clusterId: $clusterId,
            data: $data,
        ));

        return DatabaseCluster::fromJsonApi($response->json());
    }

    public function delete(string $clusterId): void
    {
        $this->connector->send(new DeleteDatabaseClusterRequest($clusterId));
    }

    public function types(): array
    {
        $response = $this->connector->send(new ListDatabaseTypesRequest);
        $responseData = $response->json();

        return collect($responseData['data'] ?? [])->map(fn ($item) => DatabaseType::fromJsonApi(['data' => $item, 'included' => $responseData['included'] ?? []]))->toArray();
    }
}
