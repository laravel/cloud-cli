<?php

namespace App\Client\Resources\Caches;

use App\Dto\CacheMetrics;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetCacheMetricsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $cacheId,
        protected ?string $period = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/caches/{$this->cacheId}/metrics";
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'period' => $this->period,
        ]);
    }

    public function createDtoFromResponse(Response $response): CacheMetrics
    {
        return CacheMetrics::createFromResponse($response->json());
    }
}
