<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\WebSocketApplications\CreateWebSocketApplicationRequest;
use App\Client\Resources\WebSocketApplications\DeleteWebSocketApplicationRequest;
use App\Client\Resources\WebSocketApplications\GetWebSocketApplicationRequest;
use App\Client\Resources\WebSocketApplications\ListWebSocketApplicationsRequest;
use App\Client\Resources\WebSocketApplications\UpdateWebSocketApplicationRequest;
use App\Dto\WebsocketApplication;

class WebSocketApplicationsResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(string $clusterId): array
    {
        $response = $this->connector->send(new ListWebSocketApplicationsRequest($clusterId));

        $responseData = $response->json();

        return collect($responseData['data'] ?? [])->map(fn ($item) => new WebsocketApplication(
            id: $item['id'],
            name: $item['attributes']['name'],
            appId: $item['attributes']['app_id'],
            allowedOrigins: $item['attributes']['allowed_origins'] ?? [],
            pingInterval: $item['attributes']['ping_interval'],
            activityTimeout: $item['attributes']['activity_timeout'],
            maxMessageSize: $item['attributes']['max_message_size'],
            maxConnections: $item['attributes']['max_connections'],
            key: $item['attributes']['key'],
            secret: $item['attributes']['secret'],
            createdAt: isset($item['attributes']['created_at']) ? \Carbon\CarbonImmutable::parse($item['attributes']['created_at']) : null,
            serverId: $item['relationships']['server']['data']['id'] ?? null,
        ))->toArray();
    }

    public function get(string $clusterId, string $applicationId): WebsocketApplication
    {
        $response = $this->connector->send(new GetWebSocketApplicationRequest(
            clusterId: $clusterId,
            applicationId: $applicationId,
        ));

        $data = $response->json()['data'];

        return new WebsocketApplication(
            id: $data['id'],
            name: $data['attributes']['name'],
            appId: $data['attributes']['app_id'],
            allowedOrigins: $data['attributes']['allowed_origins'] ?? [],
            pingInterval: $data['attributes']['ping_interval'],
            activityTimeout: $data['attributes']['activity_timeout'],
            maxMessageSize: $data['attributes']['max_message_size'],
            maxConnections: $data['attributes']['max_connections'],
            key: $data['attributes']['key'],
            secret: $data['attributes']['secret'],
            createdAt: isset($data['attributes']['created_at']) ? \Carbon\CarbonImmutable::parse($data['attributes']['created_at']) : null,
            serverId: $data['relationships']['server']['data']['id'] ?? null,
        );
    }

    public function create(string $clusterId, array $data): WebsocketApplication
    {
        $response = $this->connector->send(new CreateWebSocketApplicationRequest(
            clusterId: $clusterId,
            data: $data,
        ));

        $responseData = $response->json()['data'];

        return new WebsocketApplication(
            id: $responseData['id'],
            name: $responseData['attributes']['name'],
            appId: $responseData['attributes']['app_id'],
            allowedOrigins: $responseData['attributes']['allowed_origins'] ?? [],
            pingInterval: $responseData['attributes']['ping_interval'],
            activityTimeout: $responseData['attributes']['activity_timeout'],
            maxMessageSize: $responseData['attributes']['max_message_size'],
            maxConnections: $responseData['attributes']['max_connections'],
            key: $responseData['attributes']['key'],
            secret: $responseData['attributes']['secret'],
            createdAt: isset($responseData['attributes']['created_at']) ? \Carbon\CarbonImmutable::parse($responseData['attributes']['created_at']) : null,
            serverId: $responseData['relationships']['server']['data']['id'] ?? null,
        );
    }

    public function update(string $clusterId, string $applicationId, array $data): WebsocketApplication
    {
        $response = $this->connector->send(new UpdateWebSocketApplicationRequest(
            clusterId: $clusterId,
            applicationId: $applicationId,
            data: $data,
        ));

        $responseData = $response->json()['data'];

        return new WebsocketApplication(
            id: $responseData['id'],
            name: $responseData['attributes']['name'],
            appId: $responseData['attributes']['app_id'],
            allowedOrigins: $responseData['attributes']['allowed_origins'] ?? [],
            pingInterval: $responseData['attributes']['ping_interval'],
            activityTimeout: $responseData['attributes']['activity_timeout'],
            maxMessageSize: $responseData['attributes']['max_message_size'],
            maxConnections: $responseData['attributes']['max_connections'],
            key: $responseData['attributes']['key'],
            secret: $responseData['attributes']['secret'],
            createdAt: isset($responseData['attributes']['created_at']) ? \Carbon\CarbonImmutable::parse($responseData['attributes']['created_at']) : null,
            serverId: $responseData['relationships']['server']['data']['id'] ?? null,
        );
    }

    public function delete(string $clusterId, string $applicationId): void
    {
        $this->connector->send(new DeleteWebSocketApplicationRequest(
            clusterId: $clusterId,
            applicationId: $applicationId,
        ));
    }
}
