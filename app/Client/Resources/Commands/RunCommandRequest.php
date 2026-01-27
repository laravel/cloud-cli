<?php

namespace App\Client\Resources\Commands;

use App\Dto\Command;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class RunCommandRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected string $environmentId,
        protected string $command,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->environmentId}/commands";
    }

    protected function defaultBody(): array
    {
        return [
            'command' => $this->command,
        ];
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return Command::createFromResponse($response->json());
    }
}
