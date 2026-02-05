<?php

namespace App\Client\Resources\DatabaseSnapshots;

use App\Dto\DatabaseSnapshot;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListDatabaseSnapshotsRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $clusterId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/databases/clusters/{$this->clusterId}/snapshots";
    }

    public function createDtoFromResponse(Response $response): array
    {
        $data = $response->json('data') ?? [];

        return array_map(fn (array $item) => DatabaseSnapshot::createFromResponse(['data' => $item]), $data);
    }
}
