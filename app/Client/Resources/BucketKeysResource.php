<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\BucketKeys\CreateBucketKeyRequest;
use App\Client\Resources\BucketKeys\DeleteBucketKeyRequest;
use App\Client\Resources\BucketKeys\GetBucketKeyRequest;
use App\Client\Resources\BucketKeys\ListBucketKeysRequest;
use App\Client\Resources\BucketKeys\UpdateBucketKeyRequest;

class BucketKeysResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(string $bucketId): array
    {
        $response = $this->connector->send(new ListBucketKeysRequest($bucketId));

        return $response->json()['data'] ?? [];
    }

    public function get(string $bucketId, string $keyId): array
    {
        $response = $this->connector->send(new GetBucketKeyRequest(
            bucketId: $bucketId,
            keyId: $keyId,
        ));

        return $response->json()['data'] ?? [];
    }

    public function create(string $bucketId, string $name, string $permission): array
    {
        $response = $this->connector->send(new CreateBucketKeyRequest(
            bucketId: $bucketId,
            name: $name,
            permission: $permission,
        ));

        return $response->json()['data'] ?? [];
    }

    public function update(string $bucketId, string $keyId, array $data): array
    {
        $response = $this->connector->send(new UpdateBucketKeyRequest(
            bucketId: $bucketId,
            keyId: $keyId,
            data: $data,
        ));

        return $response->json()['data'] ?? [];
    }

    public function delete(string $bucketId, string $keyId): void
    {
        $this->connector->send(new DeleteBucketKeyRequest(
            bucketId: $bucketId,
            keyId: $keyId,
        ));
    }
}
