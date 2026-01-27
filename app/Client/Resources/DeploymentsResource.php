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

        return $this->connector->paginate($request);
    }

    public function get(string $deploymentId): Deployment
    {
        $request = new GetDeploymentRequest($deploymentId);

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function initiate(string $environmentId): Deployment
    {
        $request = new InitiateDeploymentRequest($environmentId);

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }
}
