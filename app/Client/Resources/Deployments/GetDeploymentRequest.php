<?php

namespace App\Client\Resources\Deployments;

use App\Dto\Deployment;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetDeploymentRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $deploymentId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/deployments/{$this->deploymentId}";
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return Deployment::createFromResponse($response->json());
    }
}
