<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\Commands\GetCommandRequest;
use App\Client\Resources\Commands\ListCommandsRequest;
use App\Client\Resources\Commands\RunCommandRequest;
use App\Dto\Command;
use Saloon\PaginationPlugin\Paginator;

class CommandsResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(string $environmentId): Paginator
    {
        $request = new ListCommandsRequest($environmentId);

        return $this->connector->paginate($request);
    }

    public function get(string $commandId): Command
    {
        $request = new GetCommandRequest($commandId);

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function run(string $environmentId, string $command): Command
    {
        $request = new RunCommandRequest(
            environmentId: $environmentId,
            command: $command,
        );

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }
}
