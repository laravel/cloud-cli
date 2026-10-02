<?php

namespace App\Client\Resources\Deployments;

use App\Dto\DeploymentLog;
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

    public function createDtoFromResponse(Response $response): DeploymentLog
    {
        return DeploymentLog::createFromResponse($this->deploymentId, $response->json());
    }
}
