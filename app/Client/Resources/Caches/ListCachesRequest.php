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

    public function resolveEndpoint(): string
    {
        return '/caches';
    }

    public function createDtoFromResponse(Response $response): array
    {
        $data = $response->json('data') ?? [];

        return array_map(fn (array $item) => Cache::createFromResponse(['data' => $item]), $data);
    }
}
