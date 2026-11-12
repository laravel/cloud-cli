<?php

namespace App\Client\Resources\Deployments;

use App\Client\Requests\InitiateDeploymentRequestData;
use App\Dto\Deployment;
use App\Dto\QueuedDeployment;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class InitiateDeploymentRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(
        protected InitiateDeploymentRequestData $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->data->environmentId}/deployments";
    }

    /**
     * Cloud answers 202 with a queued deployment while the environment's resources are still provisioning.
     */
    public function createDtoFromResponse(Response $response): Deployment|QueuedDeployment
    {
        if ($response->status() === 202 || $response->json('data.type') === 'queuedDeployments') {
            return QueuedDeployment::createFromResponse($response->json());
        }

        return Deployment::createFromResponse($response->json());
    }
}
