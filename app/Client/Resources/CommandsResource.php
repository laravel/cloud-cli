<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\Commands\GetCommandRequest;
use App\Client\Resources\Commands\ListCommandsRequest;
use App\Client\Resources\Commands\RunCommandRequest;
use App\Client\ResponseMapper;
use App\Dto\Command;
use App\Dto\Paginated;

class CommandsResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(string $environmentId): Paginated
    {
        $response = $this->connector->send(new ListCommandsRequest($environmentId));

        return ResponseMapper::mapPaginated($response->json(), fn ($response, $item) => ResponseMapper::mapCommand($response, $item));
    }

    public function get(string $commandId): Command
    {
        $response = $this->connector->send(new GetCommandRequest($commandId));

        return ResponseMapper::mapCommand($response->json());
    }

    public function run(string $environmentId, string $command): Command
    {
        $response = $this->connector->send(new RunCommandRequest(
            environmentId: $environmentId,
            command: $command,
        ));

        return ResponseMapper::mapCommand($response->json());
    }
}
