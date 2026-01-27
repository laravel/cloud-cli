<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\Concerns\HasIncludes;
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
    use HasIncludes;

    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(): Paginator
    {
        $request = new ListDatabaseClustersRequest(include: $this->getIncludesString());

        return $this->connector->paginate($request);
    }

    public function get(string $clusterId): DatabaseCluster
    {
        $request = new GetDatabaseClusterRequest(
            clusterId: $clusterId,
            include: $this->getIncludesString(),
        );

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(string $type, string $name, string $region, array $config, ?int $clusterId = null): DatabaseCluster
    {
        $request = new CreateDatabaseClusterRequest(
            type: $type,
            name: $name,
            region: $region,
            config: $config,
            clusterId: $clusterId,
        );

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(string $clusterId, array $data): DatabaseCluster
    {
        $request = new UpdateDatabaseClusterRequest(
            clusterId: $clusterId,
            data: $data,
        );

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $clusterId): void
    {
        $this->connector->send(new DeleteDatabaseClusterRequest($clusterId));
    }

    public function types(): array
    {
        $response = $this->connector->send(new ListDatabaseTypesRequest);
        $responseData = $response->json();

        return collect($responseData['data'] ?? [])->map(fn ($item) => DatabaseType::createFromResponse(['data' => $item, 'included' => $responseData['included'] ?? []]))->toArray();
    }
}
