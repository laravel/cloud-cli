<?php

namespace App\Client\Resources\Deployments;

use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\QueuedDeployment;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetQueuedDeploymentRequest extends Request
{
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $queuedDeploymentId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/queued-deployments/{$this->queuedDeploymentId}";
    }

    public function createDtoFromResponse(Response $response): QueuedDeployment
    {
        return QueuedDeployment::createFromResponse($response->json());
    }
}
