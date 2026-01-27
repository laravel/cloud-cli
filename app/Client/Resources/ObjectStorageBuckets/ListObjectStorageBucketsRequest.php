<?php

namespace App\Client\Resources\ObjectStorageBuckets;

use App\Dto\ObjectStorageBucket;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListObjectStorageBucketsRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(
        protected ?string $include = null,
        protected ?string $type = null,
        protected ?string $status = null,
        protected ?string $visibility = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return '/buckets';
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'include' => $this->include,
            'filter[type]' => $this->type,
            'filter[status]' => $this->status,
            'filter[visibility]' => $this->visibility,
        ]);
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return array_map(fn ($bucket) => ObjectStorageBucket::createFromResponse([
            'data' => $bucket,
            'included' => $response->json('included', []),
        ]), $response->json('data'));
    }
}
