<?php

namespace App\Client\Resources\BucketKeys;

use App\Dto\BucketKey;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListBucketKeysRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $bucketId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/buckets/{$this->bucketId}/keys";
    }

    public function createDtoFromResponse(Response $response): array
    {
        $data = $response->json('data') ?? [];

        return array_map(fn (array $item) => BucketKey::createFromResponse(['data' => $item]), $data);
    }
}
