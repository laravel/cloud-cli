<?php

namespace App\Client\Resources\Instances;

use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\EnvironmentInstance;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListInstancesRequest extends Request implements Paginatable
{
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $environmentId,
        protected ?string $name = null,
        protected ?string $type = null,
        protected ?string $size = null,
        protected ?string $scalingType = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->environmentId}/instances";
    }

    protected function defaultQuery(): array
    {
        return array_merge(
            $this->includeQuery(),
            array_filter([
                'filter[name]' => $this->name,
                'filter[type]' => $this->type,
                'filter[size]' => $this->size,
                'filter[scaling_type]' => $this->scalingType,
            ]),
        );
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return array_map(fn ($instance) => EnvironmentInstance::createFromResponse([
            'data' => $instance,
            'included' => $response->json('included', []),
        ]), $response->json('data'));
    }
}
