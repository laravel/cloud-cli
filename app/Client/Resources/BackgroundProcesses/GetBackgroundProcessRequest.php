<?php

namespace App\Client\Resources\BackgroundProcesses;

use App\Dto\BackgroundProcess;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetBackgroundProcessRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $backgroundProcessId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/background-processes/{$this->backgroundProcessId}";
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return BackgroundProcess::createFromResponse($response->json());
    }
}
