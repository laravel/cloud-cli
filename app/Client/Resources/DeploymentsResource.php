<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\Deployments\GetDeploymentRequest;
use App\Client\Resources\Deployments\InitiateDeploymentRequest;
use App\Client\Resources\Deployments\ListDeploymentsRequest;
use App\Dto\Deployment;
use Saloon\PaginationPlugin\Paginator;

class DeploymentsResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(string $environmentId): Paginator
    {
        $request = new ListDeploymentsRequest($environmentId);

        return $this->connector->paginate($request)->transform(fn ($responseData, $item) => Deployment::fromJsonApi(['data' => $item, 'included' => $responseData['included'] ?? []]));
    }

    public function get(string $deploymentId): Deployment
    {
        $response = $this->connector->send(new GetDeploymentRequest($deploymentId));

        return Deployment::fromJsonApi($response->json());
    }

    public function initiate(string $environmentId): Deployment
    {
        $response = $this->connector->send(new InitiateDeploymentRequest($environmentId));

        return Deployment::fromJsonApi($response->json());
    }
}
