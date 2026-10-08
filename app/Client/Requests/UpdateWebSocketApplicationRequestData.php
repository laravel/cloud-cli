<?php

namespace App\Client\Requests;

class UpdateWebSocketApplicationRequestData extends RequestData
{
    public function __construct(
        public readonly string $applicationId,
        public readonly ?string $name = null,
        public readonly ?array $allowedOrigins = null,
        public readonly ?int $pingInterval = null,
        public readonly ?int $activityTimeout = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'name' => $this->name,
            'allowed_origins' => $this->allowedOrigins,
            'ping_interval' => $this->pingInterval,
            'activity_timeout' => $this->activityTimeout,
        ]);
    }
}
