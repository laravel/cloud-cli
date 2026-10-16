<?php

namespace App\Client\Resources\Secrets;

use App\Dto\Secret;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListSecretsRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(
        protected ?string $key = null,
        protected ?string $sort = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return '/secrets';
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'filter[key]' => $this->key,
            'sort' => $this->sort,
        ]);
    }

    public function createDtoFromResponse(Response $response): array
    {
        $data = $response->json('data') ?? [];

        return array_map(fn (array $item) => Secret::createFromResponse(['data' => $item]), $data);
    }
}
