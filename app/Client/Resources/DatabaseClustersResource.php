<?php

namespace App\Client\Resources;

use App\Client\Requests\CreateDatabaseClusterRequestData;
use App\Client\Requests\UpdateDatabaseClusterRequestData;
use App\Client\Resources\DatabaseClusters\CreateDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\DeleteDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\GetDatabaseClusterMetricsRequest;
use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseTypesRequest;
use App\Client\Resources\DatabaseClusters\UpdateDatabaseClusterRequest;
use App\Dto\DatabaseCluster;
use Saloon\PaginationPlugin\Paginator;

class DatabaseClustersResource extends Resource
{
    public function list(): Paginator
    {
        $request = new ListDatabaseClustersRequest;

        return $this->paginate($request);
    }

    public function get(string $clusterId): DatabaseCluster
    {
        $request = new GetDatabaseClusterRequest($clusterId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(CreateDatabaseClusterRequestData $data): DatabaseCluster
    {
        $request = new CreateDatabaseClusterRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(UpdateDatabaseClusterRequestData $data): DatabaseCluster
    {
        $request = new UpdateDatabaseClusterRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $clusterId): void
    {
        $this->send(new DeleteDatabaseClusterRequest($clusterId));
    }

    public function metrics(string $clusterId): array
    {
        $request = new GetDatabaseClusterMetricsRequest($clusterId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function types(): array
    {
        $request = new ListDatabaseTypesRequest;
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }
}
