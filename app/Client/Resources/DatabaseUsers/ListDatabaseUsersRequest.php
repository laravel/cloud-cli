<?php

namespace App\Client\Resources\DatabaseUsers;

use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\DatabaseUser;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListDatabaseUsersRequest extends Request implements Paginatable
{
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $clusterId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/databases/clusters/{$this->clusterId}/users";
    }

    public function createDtoFromResponse(Response $response): array
    {
        $data = $response->json('data') ?? [];
        $included = $response->json('included') ?? [];

        return array_map(fn (array $item) => DatabaseUser::createFromResponse(['data' => $item, 'included' => $included]), $data);
    }
}
