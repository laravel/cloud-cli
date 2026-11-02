<?php

namespace App\Client\Resources\ScheduledCommands;

use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\ScheduledCommand;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetScheduledCommandRequest extends Request
{
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $scheduledCommandId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/scheduled-commands/{$this->scheduledCommandId}";
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return ScheduledCommand::createFromResponse($response->json());
    }
}
