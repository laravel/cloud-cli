<?php

namespace App\Client\Resources;

use App\Client\Requests\CreateScheduledCommandRequestData;
use App\Client\Requests\UpdateScheduledCommandRequestData;
use App\Client\Resources\ScheduledCommands\CreateScheduledCommandRequest;
use App\Client\Resources\ScheduledCommands\DeleteScheduledCommandRequest;
use App\Client\Resources\ScheduledCommands\GetScheduledCommandRequest;
use App\Client\Resources\ScheduledCommands\ListScheduledCommandsRequest;
use App\Client\Resources\ScheduledCommands\UpdateScheduledCommandRequest;
use App\Dto\ScheduledCommand;
use Saloon\PaginationPlugin\Paginator;

class ScheduledCommandsResource extends Resource
{
    public function list(string $instanceId): Paginator
    {
        $request = new ListScheduledCommandsRequest($instanceId);

        return $this->paginate($request);
    }

    public function get(string $scheduledCommandId): ScheduledCommand
    {
        $request = new GetScheduledCommandRequest($scheduledCommandId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(CreateScheduledCommandRequestData $data): ScheduledCommand
    {
        $request = new CreateScheduledCommandRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(UpdateScheduledCommandRequestData $data): ScheduledCommand
    {
        $request = new UpdateScheduledCommandRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $scheduledCommandId): void
    {
        $this->send(new DeleteScheduledCommandRequest($scheduledCommandId));
    }
}
