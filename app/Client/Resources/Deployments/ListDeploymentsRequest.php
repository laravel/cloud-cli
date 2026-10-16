<?php

namespace App\Client\Resources\Deployments;

use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\Deployment;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListDeploymentsRequest extends Request implements Paginatable
{
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $environmentId,
        protected ?string $status = null,
        protected ?string $branchName = null,
        protected ?string $commitHash = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->environmentId}/deployments";
    }

    protected function defaultQuery(): array
    {
        return array_merge(
            $this->includeQuery(),
            array_filter([
                'filter[status]' => $this->status,
                'filter[branch_name]' => $this->branchName,
                'filter[commit_hash]' => $this->commitHash,
            ]),
        );
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return array_map(fn ($deployment) => Deployment::createFromResponse([
            'data' => $deployment,
            'included' => $response->json('included', []),
        ]), $response->json('data'));
    }
}
