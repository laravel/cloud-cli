<?php

namespace App\Client\Resources;

use App\Client\Requests\InitiateDeploymentRequestData;
use App\Client\Resources\Deployments\GetDeploymentLogsRequest;
use App\Client\Resources\Deployments\GetDeploymentRequest;
use App\Client\Resources\Deployments\InitiateDeploymentRequest;
use App\Client\Resources\Deployments\ListDeploymentsRequest;
use App\Dto\Deployment;
use Saloon\PaginationPlugin\Paginator;

class DeploymentsResource extends Resource
{
    public function list(string $environmentId): Paginator
    {
        $request = new ListDeploymentsRequest($environmentId);

        return $this->paginate($request);
    }

    public function get(string $deploymentId): Deployment
    {
        $request = new GetDeploymentRequest($deploymentId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function logs(string $deploymentId): array
    {
        $request = new GetDeploymentLogsRequest($deploymentId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function initiate(InitiateDeploymentRequestData $data): Deployment
    {
        $request = new InitiateDeploymentRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }
}
