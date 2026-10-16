<?php

namespace App\Client\Resources;

use App\Client\Requests\RunCommandRequestData;
use App\Client\Resources\Commands\GetCommandRequest;
use App\Client\Resources\Commands\ListCommandsRequest;
use App\Client\Resources\Commands\RunCommandRequest;
use App\Dto\Command;
use Saloon\PaginationPlugin\Paginator;

class CommandsResource extends Resource
{
    public function list(string $environmentId, ?string $status = null, ?string $command = null): Paginator
    {
        $request = new ListCommandsRequest(
            environmentId: $environmentId,
            status: $status,
            command: $command,
        );

        return $this->paginate($request);
    }

    public function get(string $commandId): Command
    {
        $request = new GetCommandRequest($commandId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function run(RunCommandRequestData $data): Command
    {
        $request = new RunCommandRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }
}
