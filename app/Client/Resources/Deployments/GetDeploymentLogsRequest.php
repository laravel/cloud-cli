<?php

namespace App\Client\Resources\Deployments;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetDeploymentLogsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $deploymentId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/deployments/{$this->deploymentId}/logs";
    }

    /**
     * @return array<int, array{type: string, timestamp: string|null, output: string}>
     */
    public function createDtoFromResponse(Response $response): array
    {
        return $response->json('data', []);
    }
}
