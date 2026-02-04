<?php

namespace App\Client\Resources\Caches;

use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\Cache;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetCacheRequest extends Request
{
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $cacheId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/caches/{$this->cacheId}";
    }

    public function createDtoFromResponse(Response $response): Cache
    {
        return Cache::createFromResponse($response->json());
    }
}
