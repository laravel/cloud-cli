<?php

namespace App\Client\Resources\ScheduledCommands;

use App\Client\Requests\UpdateScheduledCommandRequestData;
use App\Dto\ScheduledCommand;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class UpdateScheduledCommandRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    public function __construct(
        protected UpdateScheduledCommandRequestData $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/scheduled-commands/{$this->data->scheduledCommandId}";
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
