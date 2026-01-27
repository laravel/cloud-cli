<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\Instances\CreateInstanceRequest;
use App\Client\Resources\Instances\DeleteInstanceRequest;
use App\Client\Resources\Instances\GetInstanceRequest;
use App\Client\Resources\Instances\ListInstanceSizesRequest;
use App\Client\Resources\Instances\ListInstancesRequest;
use App\Client\Resources\Instances\UpdateInstanceRequest;
use App\Client\ResponseMapper;
use App\Dto\EnvironmentInstance;
use App\Dto\Paginated;

class InstancesResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(string $environmentId): Paginated
    {
        $response = $this->connector->send(new ListInstancesRequest($environmentId));

        return ResponseMapper::mapPaginated($response->json(), fn ($response, $item) => ResponseMapper::mapEnvironmentInstance($response, $item));
    }

    public function get(string $instanceId): EnvironmentInstance
    {
        $response = $this->connector->send(new GetInstanceRequest($instanceId));

        return ResponseMapper::mapEnvironmentInstance($response->json());
    }

    public function create(string $environmentId, array $data): EnvironmentInstance
    {
        $response = $this->connector->send(new CreateInstanceRequest(
            environmentId: $environmentId,
            data: $data,
        ));

        return ResponseMapper::mapEnvironmentInstance($response->json());
    }

    public function update(string $instanceId, array $data): EnvironmentInstance
    {
        $response = $this->connector->send(new UpdateInstanceRequest(
            instanceId: $instanceId,
            data: $data,
        ));

        return ResponseMapper::mapEnvironmentInstance($response->json());
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
