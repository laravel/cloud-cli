<?php

namespace App\Client\Resources;

use App\Client\Requests\CreateWebSocketApplicationRequestData;
use App\Client\Requests\UpdateWebSocketApplicationRequestData;
use App\Client\Resources\WebSocketApplications\CreateWebSocketApplicationRequest;
use App\Client\Resources\WebSocketApplications\DeleteWebSocketApplicationRequest;
use App\Client\Resources\WebSocketApplications\GetWebSocketApplicationRequest;
use App\Client\Resources\WebSocketApplications\ListWebSocketApplicationsRequest;
use App\Client\Resources\WebSocketApplications\UpdateWebSocketApplicationRequest;
use App\Dto\WebsocketApplication;
use Saloon\PaginationPlugin\Paginator;

class WebSocketApplicationsResource extends Resource
{
    public function list(string $clusterId): Paginator
    {
        $request = new ListWebSocketApplicationsRequest($clusterId);

        return $this->paginate($request);
    }

    public function get(string $applicationId): WebsocketApplication
    {
        $request = new GetWebSocketApplicationRequest(
            applicationId: $applicationId,
        );
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(CreateWebSocketApplicationRequestData $data): WebsocketApplication
    {
        $request = new CreateWebSocketApplicationRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(UpdateWebSocketApplicationRequestData $data): WebsocketApplication
    {
        $request = new UpdateWebSocketApplicationRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $clusterId, string $applicationId): void
    {
        $this->send(new DeleteWebSocketApplicationRequest(
            clusterId: $clusterId,
            applicationId: $applicationId,
        ));
    }
}
