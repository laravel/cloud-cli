<?php

namespace App\Client\Resources\Commands;

use App\Dto\Command;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetCommandRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $commandId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/commands/{$this->commandId}";
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return Command::createFromResponse($response->json());
    }
}
