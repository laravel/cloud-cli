<?php

namespace App\Client\Resources\ObjectStorageBuckets;

use Saloon\Enums\Method;
use Saloon\Http\Request;
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
}
