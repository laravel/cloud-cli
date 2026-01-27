<?php

namespace App\Client\Resources\Databases;

use App\Dto\Database;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListDatabasesRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $clusterId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/databases/clusters/{$this->clusterId}/databases";
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return array_map(fn ($database) => Database::createFromResponse([
            'data' => $database,
            'included' => $response->json('included', []),
        ]), $response->json('data'));
    }
}
