<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\Instances\CreateInstanceRequest;
use App\Client\Resources\Instances\DeleteInstanceRequest;
use App\Client\Resources\Instances\GetInstanceRequest;
use App\Client\Resources\Instances\ListInstanceSizesRequest;
use App\Client\Resources\Instances\ListInstancesRequest;
use App\Client\Resources\Instances\UpdateInstanceRequest;
use App\Dto\EnvironmentInstance;
use Saloon\PaginationPlugin\Paginator;

class InstancesResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(string $environmentId): Paginator
    {
        $request = new ListInstancesRequest($environmentId);

        return $this->connector->paginate($request);
    }

    public function get(string $instanceId): EnvironmentInstance
    {
        $request = new GetInstanceRequest($instanceId);

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(string $environmentId, array $data): EnvironmentInstance
    {
        $request = new CreateInstanceRequest(
            environmentId: $environmentId,
            data: $data,
        );

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(string $instanceId, array $data): EnvironmentInstance
    {
        $request = new UpdateInstanceRequest(
            instanceId: $instanceId,
            data: $data,
        );

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $instanceId): void
    {
        $this->connector->send(new DeleteInstanceRequest($instanceId));
    }

    public function sizes(): array
    {
        $response = $this->connector->send(new ListInstanceSizesRequest);

        return $response->json()['data'] ?? [];
    }
}
