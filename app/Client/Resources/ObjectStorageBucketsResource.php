<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\Concerns\HasIncludes;
use App\Client\Resources\ObjectStorageBuckets\CreateObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\DeleteObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\GetObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\ListObjectStorageBucketsRequest;
use App\Client\Resources\ObjectStorageBuckets\UpdateObjectStorageBucketRequest;
use App\Dto\ObjectStorageBucket;
use Saloon\PaginationPlugin\Paginator;

class ObjectStorageBucketsResource
{
    use HasIncludes;

    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(?string $type = null, ?string $status = null, ?string $visibility = null): Paginator
    {
        $request = new ListObjectStorageBucketsRequest(
            include: $this->getIncludesString(),
            type: $type,
            status: $status,
            visibility: $visibility,
        );

        return $this->connector->paginate($request);
    }

    public function get(string $bucketId): ObjectStorageBucket
    {
        $request = new GetObjectStorageBucketRequest(
            bucketId: $bucketId,
            include: $this->getIncludesString(),
        );

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(string $name, string $region, string $visibility, ?string $jurisdiction = null, ?array $allowedOrigins = null, ?string $keyName = null, ?string $keyPermission = null): ObjectStorageBucket
    {
        $request = new CreateObjectStorageBucketRequest(
            name: $name,
            region: $region,
            visibility: $visibility,
            jurisdiction: $jurisdiction,
            allowedOrigins: $allowedOrigins,
            keyName: $keyName,
            keyPermission: $keyPermission,
        );

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(string $bucketId, array $data): ObjectStorageBucket
    {
        $request = new UpdateObjectStorageBucketRequest(
            bucketId: $bucketId,
            data: $data,
        );

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $bucketId): void
    {
        $this->connector->send(new DeleteObjectStorageBucketRequest($bucketId));
    }
}
