<?php

namespace App\Client\Resources\CodeExecutions;

use App\Dto\CodeExecution;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetCodeExecutionRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $codeExecutionId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/code-executions/{$this->codeExecutionId}";
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return CodeExecution::createFromResponse($response->json());
    }
}
