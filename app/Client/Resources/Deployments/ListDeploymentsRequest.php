<?php

namespace App\Client\Resources\Deployments;

use App\Dto\Deployment;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListDeploymentsRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

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
        return array_map(fn ($deployment) => Deployment::createFromResponse([
            'data' => $deployment,
            'included' => $response->json('included', []),
        ]), $response->json('data'));
    }
}
