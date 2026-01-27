<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\BackgroundProcesses\CreateBackgroundProcessRequest;
use App\Client\Resources\BackgroundProcesses\DeleteBackgroundProcessRequest;
use App\Client\Resources\BackgroundProcesses\GetBackgroundProcessRequest;
use App\Client\Resources\BackgroundProcesses\ListBackgroundProcessesRequest;
use App\Client\Resources\BackgroundProcesses\UpdateBackgroundProcessRequest;
use App\Dto\BackgroundProcess;
use Saloon\PaginationPlugin\Paginator;

class BackgroundProcessesResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(string $instanceId): Paginator
    {
        $request = new ListBackgroundProcessesRequest($instanceId);

        return $this->connector->paginate($request);
    }

    public function get(string $backgroundProcessId): BackgroundProcess
    {
        $request = new GetBackgroundProcessRequest($backgroundProcessId);

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(string $instanceId, array $data): BackgroundProcess
    {
        $request = new CreateBackgroundProcessRequest(
            instanceId: $instanceId,
            data: $data,
        );

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(string $backgroundProcessId, array $data): BackgroundProcess
    {
        $request = new UpdateBackgroundProcessRequest(
            backgroundProcessId: $backgroundProcessId,
            data: $data,
        );

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $backgroundProcessId): void
    {
        $this->connector->send(new DeleteBackgroundProcessRequest($backgroundProcessId));
    }
}
