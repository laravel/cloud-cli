<?php

namespace App\Client\Resources;

use App\Client\Requests\CreateWebSocketClusterRequestData;
use App\Client\Requests\UpdateWebSocketClusterRequestData;
use App\Client\Resources\WebSocketClusters\CreateWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\DeleteWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\GetWebSocketClusterMetricsRequest;
use App\Client\Resources\WebSocketClusters\GetWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\ListWebSocketClustersRequest;
use App\Client\Resources\WebSocketClusters\ListWebSocketClusterTypesRequest;
use App\Client\Resources\WebSocketClusters\StartWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\StopWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\UpdateWebSocketClusterRequest;
use App\Dto\WebsocketCluster;
use App\Dto\WebsocketClusterType;
use App\Dto\WebsocketMetrics;
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

    public function metrics(string $clusterId, ?string $period = null): WebsocketMetrics
    {
        $request = new GetWebSocketClusterMetricsRequest($clusterId, $period);
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

    public function start(string $clusterId): WebsocketCluster
    {
        $request = new StartWebSocketClusterRequest($clusterId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function stop(string $clusterId): WebsocketCluster
    {
        $request = new StopWebSocketClusterRequest($clusterId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $clusterId): void
    {
        $this->send(new DeleteWebSocketClusterRequest($clusterId));
    }

    /**
     * @return array<WebsocketClusterType>
     */
    public function types(): array
    {
        $request = new ListWebSocketClusterTypesRequest;
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function withDefaultIncludes(): static
    {
        return $this->include('applications');
    }
}
