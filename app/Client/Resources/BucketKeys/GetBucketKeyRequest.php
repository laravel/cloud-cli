<?php

namespace App\Client\Resources\BucketKeys;

use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\BucketKey;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetBucketKeyRequest extends Request
{
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $bucketId,
        protected string $keyId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/buckets/{$this->bucketId}/keys/{$this->keyId}";
    }

    public function createDtoFromResponse(Response $response): BucketKey
    {
        return BucketKey::createFromResponse($response->json());
    }
}
