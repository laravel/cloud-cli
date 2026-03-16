<?php

namespace App\Client\Resources\Caches;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetCacheMetricsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $cacheId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/caches/{$this->cacheId}/metrics";
    }

    public function createDtoFromResponse(Response $response): array
    {
        return $response->json('data') ?? $response->json();
    }
}
