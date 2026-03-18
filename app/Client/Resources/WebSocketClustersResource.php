<?php

namespace App\Client\Resources;

use App\Client\Requests\CreateWebSocketClusterRequestData;
use App\Client\Requests\UpdateWebSocketClusterRequestData;
use App\Client\Resources\WebSocketClusters\CreateWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\DeleteWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\GetWebSocketClusterMetricsRequest;
use App\Client\Resources\WebSocketClusters\GetWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\ListWebSocketClustersRequest;
use App\Client\Resources\WebSocketClusters\UpdateWebSocketClusterRequest;
use App\Dto\WebsocketCluster;
use Saloon\PaginationPlugin\Paginator;

class WebSocketClustersResource extends Resource
{
    public function list(): Paginator
    {
        $request = new ListWebSocketClustersRequest;

        return $this->paginate($request);
    }

    public function get(string $clusterId): WebsocketCluster
    {
        $request = new GetWebSocketClusterRequest($clusterId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(CreateWebSocketClusterRequestData $data): WebsocketCluster
    {
        $request = new CreateWebSocketClusterRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(UpdateWebSocketClusterRequestData $data): WebsocketCluster
    {
        $request = new UpdateWebSocketClusterRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $clusterId): void
    {
        $this->send(new DeleteWebSocketClusterRequest($clusterId));
    }

    public function metrics(string $clusterId): array
    {
        $request = new GetWebSocketClusterMetricsRequest($clusterId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }
}
