<?php

namespace App\Client\Resources\Caches;

use App\Dto\Cache;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class StopCacheRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(
        protected string $cacheId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/caches/{$this->cacheId}/stop";
    }

    public function createDtoFromResponse(Response $response): Cache
    {
        return Cache::createFromResponse($response->json());
    }
}
