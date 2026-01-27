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

        return $this->connector->paginate($request)->transform(fn ($responseData, $item) => Command::fromJsonApi(['data' => $item, 'included' => $responseData['included'] ?? []]));
    }

    public function get(string $commandId): Command
    {
        $response = $this->connector->send(new GetCommandRequest($commandId));

        return Command::fromJsonApi($response->json());
    }

    public function run(string $environmentId, string $command): Command
    {
        $response = $this->connector->send(new RunCommandRequest(
            environmentId: $environmentId,
            command: $command,
        ));

        return Command::fromJsonApi($response->json());
    }
}
