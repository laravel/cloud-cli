<?php

namespace App\Client\Resources\ScheduledCommands;

use App\Client\Requests\CreateScheduledCommandRequestData;
use App\Dto\ScheduledCommand;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class CreateScheduledCommandRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected CreateScheduledCommandRequestData $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/instances/{$this->data->instanceId}/scheduled-commands";
    }

    protected function defaultBody(): array
    {
        return $this->data->toRequestData();
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return ScheduledCommand::createFromResponse($response->json());
    }
}
