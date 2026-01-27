<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\BackgroundProcesses\CreateBackgroundProcessRequest;
use App\Client\Resources\BackgroundProcesses\DeleteBackgroundProcessRequest;
use App\Client\Resources\BackgroundProcesses\GetBackgroundProcessRequest;
use App\Client\Resources\BackgroundProcesses\ListBackgroundProcessesRequest;
use App\Client\Resources\BackgroundProcesses\UpdateBackgroundProcessRequest;
use App\Client\ResponseMapper;
use App\Dto\BackgroundProcess;
use App\Dto\Paginated;

class BackgroundProcessesResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(string $instanceId): Paginated
    {
        $response = $this->connector->send(new ListBackgroundProcessesRequest($instanceId));

        return ResponseMapper::mapPaginated($response->json(), fn ($response, $item) => ResponseMapper::mapBackgroundProcess($response, $item));
    }

    public function get(string $backgroundProcessId): BackgroundProcess
    {
        $response = $this->connector->send(new GetBackgroundProcessRequest($backgroundProcessId));

        return ResponseMapper::mapBackgroundProcess($response->json());
    }

    public function create(string $instanceId, array $data): BackgroundProcess
    {
        $response = $this->connector->send(new CreateBackgroundProcessRequest(
            instanceId: $instanceId,
            data: $data,
        ));

        return ResponseMapper::mapBackgroundProcess($response->json());
    }

    public function update(string $backgroundProcessId, array $data): BackgroundProcess
    {
        $response = $this->connector->send(new UpdateBackgroundProcessRequest(
            backgroundProcessId: $backgroundProcessId,
            data: $data,
        ));

        return ResponseMapper::mapBackgroundProcess($response->json());
    }

    public function delete(string $backgroundProcessId): void
    {
        $this->connector->send(new DeleteBackgroundProcessRequest($backgroundProcessId));
    }
}
