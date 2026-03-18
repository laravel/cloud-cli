<?php

namespace App\Client\Resources;

use App\Client\Requests\CreateCacheRequestData;
use App\Client\Requests\UpdateCacheRequestData;
use App\Client\Resources\Caches\CreateCacheRequest;
use App\Client\Resources\Caches\DeleteCacheRequest;
use App\Client\Resources\Caches\GetCacheMetricsRequest;
use App\Client\Resources\Caches\GetCacheRequest;
use App\Client\Resources\Caches\ListCachesRequest;
use App\Client\Resources\Caches\ListCacheTypesRequest;
use App\Client\Resources\Caches\UpdateCacheRequest;
use App\Dto\Cache;
use App\Dto\CacheType;
use Saloon\PaginationPlugin\Paginator;

class CachesResource extends Resource
{
    public function list(): Paginator
    {
        $request = new ListCachesRequest;

        return $this->paginate($request);
    }

    public function get(string $cacheId): Cache
    {
        $request = new GetCacheRequest($cacheId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(CreateCacheRequestData $data): Cache
    {
        $request = new CreateCacheRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(UpdateCacheRequestData $data): Cache
    {
        $request = new UpdateCacheRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $cacheId): void
    {
        $this->send(new DeleteCacheRequest($cacheId));
    }

    public function metrics(string $cacheId): array
    {
        $request = new GetCacheMetricsRequest($cacheId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    /**
     * @return array<CacheType>
     */
    public function types(): array
    {
        $request = new ListCacheTypesRequest;
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }
}
