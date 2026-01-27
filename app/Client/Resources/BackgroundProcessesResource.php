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

        return $this->connector->paginate($request)->transform(function ($response) {
            $responseData = $response->json();

            return collect($responseData['data'] ?? [])->map(fn ($item) => BackgroundProcess::fromJsonApi(['data' => $item, 'included' => $responseData['included'] ?? []]))->toArray();
        });
    }

    public function get(string $backgroundProcessId): BackgroundProcess
    {
        $response = $this->connector->send(new GetBackgroundProcessRequest($backgroundProcessId));

        return BackgroundProcess::fromJsonApi($response->json());
    }

    public function create(string $instanceId, array $data): BackgroundProcess
    {
        $response = $this->connector->send(new CreateBackgroundProcessRequest(
            instanceId: $instanceId,
            data: $data,
        ));

        return BackgroundProcess::fromJsonApi($response->json());
    }

    public function update(string $backgroundProcessId, array $data): BackgroundProcess
    {
        $response = $this->connector->send(new UpdateBackgroundProcessRequest(
            backgroundProcessId: $backgroundProcessId,
            data: $data,
        ));

        return BackgroundProcess::fromJsonApi($response->json());
    }

    public function delete(string $backgroundProcessId): void
    {
        $this->connector->send(new DeleteBackgroundProcessRequest($backgroundProcessId));
    }
}
