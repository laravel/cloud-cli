<?php

namespace App\Client\Resources\ObjectStorageBuckets;

use App\Dto\ObjectStorageBucket;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class CreateObjectStorageBucketRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected string $name,
        protected string $region,
        protected string $visibility,
        protected ?string $jurisdiction = null,
        protected ?array $allowedOrigins = null,
        protected ?string $keyName = null,
        protected ?string $keyPermission = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return '/buckets';
    }

    protected function defaultBody(): array
    {
        return array_filter([
            'name' => $this->name,
            'region' => $this->region,
            'visibility' => $this->visibility,
            'jurisdiction' => $this->jurisdiction,
            'allowed_origins' => $this->allowedOrigins,
            'key_name' => $this->keyName,
            'key_permission' => $this->keyPermission,
        ]);
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return ObjectStorageBucket::createFromResponse($response->json());
    }
}
