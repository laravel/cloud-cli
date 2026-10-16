<?php

namespace App\Client\Requests;

class UpdateObjectStorageBucketRequestData extends RequestData
{
    public function __construct(
        public readonly string $bucketId,
        public readonly ?string $name = null,
        public readonly ?string $visibility = null,
        public readonly ?BucketCorsSettingsData $corsSettings = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'name' => $this->name,
            'visibility' => $this->visibility,
            'cors_settings' => $this->corsSettings?->isEmpty() === false ? $this->corsSettings->toRequestData() : null,
        ]);
    }
}
