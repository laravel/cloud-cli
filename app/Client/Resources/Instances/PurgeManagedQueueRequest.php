<?php

namespace App\Client\Resources\Instances;

use App\Dto\EnvironmentInstance;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class PurgeManagedQueueRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(
        protected string $instanceId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/instances/{$this->instanceId}/purge";
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return EnvironmentInstance::createFromResponse($response->json());
    }
}
