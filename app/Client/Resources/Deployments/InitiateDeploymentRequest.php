<?php

namespace App\Client\Resources\Deployments;

use App\Dto\Deployment;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class InitiateDeploymentRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(
        protected string $environmentId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->environmentId}/deployments";
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return Deployment::createFromResponse($response->json());
    }
}
