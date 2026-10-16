<?php

namespace App\Client\Resources\Caches;

use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\Cache;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListCachesRequest extends Request implements Paginatable
{
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function __construct(
        protected ?string $type = null,
        protected ?string $region = null,
        protected ?string $status = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return '/caches';
    }

    protected function defaultQuery(): array
    {
        return array_merge(
            $this->includeQuery(),
            array_filter([
                'filter[type]' => $this->type,
                'filter[region]' => $this->region,
                'filter[status]' => $this->status,
            ]),
        );
    }

    public function createDtoFromResponse(Response $response): array
    {
        $data = $response->json('data') ?? [];

        return array_map(fn (array $item) => Cache::createFromResponse(['data' => $item]), $data);
    }
}
