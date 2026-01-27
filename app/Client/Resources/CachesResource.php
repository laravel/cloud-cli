<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\Caches\CreateCacheRequest;
use App\Client\Resources\Caches\DeleteCacheRequest;
use App\Client\Resources\Caches\GetCacheRequest;
use App\Client\Resources\Caches\ListCachesRequest;
use App\Client\Resources\Caches\ListCacheTypesRequest;
use App\Client\Resources\Caches\UpdateCacheRequest;

class CachesResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(?string $include = null): array
    {
        $response = $this->connector->send(new ListCachesRequest(include: $include));

        return $response->json()['data'] ?? [];
    }

    public function get(string $cacheId, ?string $include = null): array
    {
        $response = $this->connector->send(new GetCacheRequest(
            cacheId: $cacheId,
            include: $include,
        ));

        return $response->json()['data'] ?? [];
    }

    public function create(string $type, string $name, string $region, array $config): array
    {
        $response = $this->connector->send(new CreateCacheRequest(
            type: $type,
            name: $name,
            region: $region,
            config: $config,
        ));

        return $response->json()['data'] ?? [];
    }

    public function update(string $cacheId, array $data): array
    {
        $response = $this->connector->send(new UpdateCacheRequest(
            cacheId: $cacheId,
            data: $data,
        ));

        return $response->json()['data'] ?? [];
    }

    public function delete(string $cacheId): void
    {
        $this->connector->send(new DeleteCacheRequest($cacheId));
    }

    public function types(): array
    {
        $response = $this->connector->send(new ListCacheTypesRequest);

        return $response->json()['data'] ?? [];
    }
}
