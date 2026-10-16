<?php

namespace App\Client\Requests;

class BucketCorsSettingsData extends RequestData
{
    public function __construct(
        public readonly ?array $allowedOrigins = null,
        public readonly ?array $allowedMethods = null,
        public readonly ?array $allowedHeaders = null,
        public readonly ?array $exposeHeaders = null,
        public readonly ?int $maxAgeSeconds = null,
    ) {
        //
    }

    public function isEmpty(): bool
    {
        return $this->toRequestData() === [];
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'allowed_origins' => $this->allowedOrigins,
            'allowed_methods' => $this->allowedMethods,
            'allowed_headers' => $this->allowedHeaders,
            'expose_headers' => $this->exposeHeaders,
            'max_age_seconds' => $this->maxAgeSeconds,
        ]);
    }
}
