<?php

namespace App\Client\Resources;

use App\Client\Resources\BucketKeys\CreateBucketKeyRequest;
use App\Client\Resources\BucketKeys\DeleteBucketKeyRequest;
use App\Client\Resources\BucketKeys\GetBucketKeyRequest;
use App\Client\Resources\BucketKeys\ListBucketKeysRequest;
use App\Client\Resources\BucketKeys\UpdateBucketKeyRequest;
use App\Dto\BucketKey;

class BucketKeysResource extends Resource
{
    /**
     * @return array<int, BucketKey>
     */
    public function list(string $bucketId): array
    {
        $response = $this->send(new ListBucketKeysRequest($bucketId));
        $data = $response->json()['data'] ?? [];

        return collect($data)->map(fn (array $item) => BucketKey::createFromResponse(['data' => $item]))->all();
    }

    public function get(string $bucketId, string $keyId): BucketKey
    {
        $request = new GetBucketKeyRequest(
            bucketId: $bucketId,
            keyId: $keyId,
        );
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(string $bucketId, string $name, string $permission): BucketKey
    {
        $response = $this->send(new CreateBucketKeyRequest(
            bucketId: $bucketId,
            name: $name,
            permission: $permission,
        ));
        $data = $response->json()['data'] ?? [];

        return BucketKey::createFromResponse(['data' => $data]);
    }

    public function update(string $bucketId, string $keyId, array $data): BucketKey
    {
        $response = $this->send(new UpdateBucketKeyRequest(
            bucketId: $bucketId,
            keyId: $keyId,
            data: $data,
        ));
        $responseData = $response->json()['data'] ?? [];

        return BucketKey::createFromResponse(['data' => $responseData]);
    }

    public function delete(string $bucketId, string $keyId): void
    {
        $this->send(new DeleteBucketKeyRequest(
            bucketId: $bucketId,
            keyId: $keyId,
        ));
    }
}
