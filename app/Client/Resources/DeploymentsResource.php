<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\Deployments\GetDeploymentRequest;
use App\Client\Resources\Deployments\InitiateDeploymentRequest;
use App\Client\Resources\Deployments\ListDeploymentsRequest;
use App\Client\ResponseMapper;
use App\Dto\Deployment;
use App\Dto\Paginated;

class DeploymentsResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(string $environmentId): Paginated
    {
        $response = $this->connector->send(new ListDeploymentsRequest($environmentId));

        return ResponseMapper::mapPaginated($response->json(), fn ($response, $item) => ResponseMapper::mapDeployment($response, $item));
    }

    public function get(string $deploymentId): Deployment
    {
        $response = $this->connector->send(new GetDeploymentRequest($deploymentId));

        return ResponseMapper::mapDeployment($response->json());
    }

    public function initiate(string $environmentId): Deployment
    {
        $response = $this->connector->send(new InitiateDeploymentRequest($environmentId));

        return ResponseMapper::mapDeployment($response->json());
    }
}
