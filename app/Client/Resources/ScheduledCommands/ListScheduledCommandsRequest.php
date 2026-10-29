<?php

namespace App\Client\Resources\ScheduledCommands;

use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\ScheduledCommand;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListScheduledCommandsRequest extends Request implements Paginatable
{
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $instanceId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/instances/{$this->instanceId}/scheduled-commands";
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return array_map(fn ($scheduledCommand) => ScheduledCommand::createFromResponse([
            'data' => $scheduledCommand,
            'included' => $response->json('included', []),
        ]), $response->json('data'));
    }
}
