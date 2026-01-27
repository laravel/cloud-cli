<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\ObjectStorageBuckets\CreateObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\DeleteObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\GetObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\ListObjectStorageBucketsRequest;
use App\Client\Resources\ObjectStorageBuckets\UpdateObjectStorageBucketRequest;
use App\Dto\ObjectStorageBucket;
use Saloon\PaginationPlugin\Paginator;

class ObjectStorageBucketsResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(?string $include = null, ?string $type = null, ?string $status = null, ?string $visibility = null): Paginator
    {
        $request = new ListObjectStorageBucketsRequest(
            include: $include,
            type: $type,
            status: $status,
            visibility: $visibility,
        );

        return $this->connector->paginate($request)->transform(function ($response) {
            $responseData = $response->json();

            return collect($responseData['data'] ?? [])->map(fn ($item) => ObjectStorageBucket::fromJsonApi(['data' => $item, 'included' => $responseData['included'] ?? []]))->toArray();
        });
    }

    public function get(string $bucketId, ?string $include = null): ObjectStorageBucket
    {
        $response = $this->connector->send(new GetObjectStorageBucketRequest(
            bucketId: $bucketId,
            include: $include,
        ));

        $data = $response->json()['data'];

        return new ObjectStorageBucket(
            id: $data['id'],
            name: $data['attributes']['name'],
            type: \App\Enums\FilesystemType::from($data['attributes']['type']),
            status: \App\Enums\FilesystemStatus::from($data['attributes']['status']),
            visibility: \App\Enums\FilesystemVisibility::from($data['attributes']['visibility']),
            jurisdiction: \App\Enums\FilesystemJurisdiction::from($data['attributes']['jurisdiction']),
            endpoint: $data['attributes']['endpoint'] ?? null,
            url: $data['attributes']['url'] ?? null,
            allowedOrigins: $data['attributes']['allowed_origins'] ?? null,
            createdAt: isset($data['attributes']['created_at']) ? \Carbon\CarbonImmutable::parse($data['attributes']['created_at']) : null,
            keyIds: array_column($data['relationships']['keys']['data'] ?? [], 'id'),
        );
    }

    public function create(string $name, string $region, string $visibility, ?string $jurisdiction = null, ?array $allowedOrigins = null, ?string $keyName = null, ?string $keyPermission = null): ObjectStorageBucket
    {
        $response = $this->connector->send(new CreateObjectStorageBucketRequest(
            name: $name,
            region: $region,
            visibility: $visibility,
            jurisdiction: $jurisdiction,
            allowedOrigins: $allowedOrigins,
            keyName: $keyName,
            keyPermission: $keyPermission,
        ));

        $data = $response->json()['data'];

        return new ObjectStorageBucket(
            id: $data['id'],
            name: $data['attributes']['name'],
            type: \App\Enums\FilesystemType::from($data['attributes']['type']),
            status: \App\Enums\FilesystemStatus::from($data['attributes']['status']),
            visibility: \App\Enums\FilesystemVisibility::from($data['attributes']['visibility']),
            jurisdiction: \App\Enums\FilesystemJurisdiction::from($data['attributes']['jurisdiction']),
            endpoint: $data['attributes']['endpoint'] ?? null,
            url: $data['attributes']['url'] ?? null,
            allowedOrigins: $data['attributes']['allowed_origins'] ?? null,
            createdAt: isset($data['attributes']['created_at']) ? \Carbon\CarbonImmutable::parse($data['attributes']['created_at']) : null,
            keyIds: array_column($data['relationships']['keys']['data'] ?? [], 'id'),
        );
    }

    public function update(string $bucketId, array $data): ObjectStorageBucket
    {
        $response = $this->connector->send(new UpdateObjectStorageBucketRequest(
            bucketId: $bucketId,
            data: $data,
        ));

        $responseData = $response->json()['data'];

        return new ObjectStorageBucket(
            id: $responseData['id'],
            name: $responseData['attributes']['name'],
            type: \App\Enums\FilesystemType::from($responseData['attributes']['type']),
            status: \App\Enums\FilesystemStatus::from($responseData['attributes']['status']),
            visibility: \App\Enums\FilesystemVisibility::from($responseData['attributes']['visibility']),
            jurisdiction: \App\Enums\FilesystemJurisdiction::from($responseData['attributes']['jurisdiction']),
            endpoint: $responseData['attributes']['endpoint'] ?? null,
            url: $responseData['attributes']['url'] ?? null,
            allowedOrigins: $responseData['attributes']['allowed_origins'] ?? null,
            createdAt: isset($responseData['attributes']['created_at']) ? \Carbon\CarbonImmutable::parse($responseData['attributes']['created_at']) : null,
            keyIds: array_column($responseData['relationships']['keys']['data'] ?? [], 'id'),
        );
    }

    public function delete(string $bucketId): void
    {
        $this->connector->send(new DeleteObjectStorageBucketRequest($bucketId));
    }
}
