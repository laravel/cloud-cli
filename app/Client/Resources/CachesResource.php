<?php

namespace App\Client\Resources;

use App\Client\Resources\Caches\CreateCacheRequest;
use App\Client\Resources\Caches\DeleteCacheRequest;
use App\Client\Resources\Caches\GetCacheRequest;
use App\Client\Resources\Caches\ListCachesRequest;
use App\Client\Resources\Caches\ListCacheTypesRequest;
use App\Client\Resources\Caches\UpdateCacheRequest;
use App\Dto\Cache;

class CachesResource extends Resource
{
    /**
     * @return array<int, Cache>
     */
    public function list(): array
    {
        $response = $this->send(new ListCachesRequest);
        $data = $response->json()['data'] ?? [];

        return collect($data)->map(fn (array $item) => Cache::createFromResponse(['data' => $item]))->all();
    }

    public function get(string $cacheId): Cache
    {
        $request = new GetCacheRequest($cacheId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(string $type, string $name, string $region, array $config): Cache
    {
        $response = $this->send(new CreateCacheRequest(
            type: $type,
            name: $name,
            region: $region,
            config: $config,
        ));

        $data = $response->json()['data'] ?? [];

        return Cache::createFromResponse(['data' => $data]);
    }

    public function update(string $cacheId, array $data): Cache
    {
        $response = $this->send(new UpdateCacheRequest(
            cacheId: $cacheId,
            data: $data,
        ));

        $responseData = $response->json()['data'] ?? [];

        return Cache::createFromResponse(['data' => $responseData]);
    }

    public function delete(string $cacheId): void
    {
        $this->send(new DeleteCacheRequest($cacheId));
    }

    public function types(): array
    {
        $response = $this->send(new ListCacheTypesRequest);

        return $response->json()['data'] ?? [];
    }
}
