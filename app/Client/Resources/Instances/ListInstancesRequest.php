<?php

namespace App\Client\Resources\Instances;

use App\Dto\EnvironmentInstance;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListInstancesRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $environmentId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->environmentId}/instances";
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return array_map(fn ($instance) => EnvironmentInstance::createFromResponse([
            'data' => $instance,
            'included' => $response->json('included', []),
        ]), $response->json('data'));
    }
}
