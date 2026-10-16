<?php

namespace App\Client\Requests;

class CreateObjectStorageBucketRequestData extends RequestData
{
    public function __construct(
        public readonly string $name,
        public readonly string $visibility,
        public readonly string $jurisdiction,
        public readonly string $keyName,
        public readonly string $keyPermission,
        public readonly ?string $locationHint = null,
        public readonly ?BucketCorsSettingsData $corsSettings = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'name' => $this->name,
            'visibility' => $this->visibility,
            'jurisdiction' => $this->jurisdiction,
            'location_hint' => $this->locationHint,
            'cors_settings' => $this->corsSettings?->isEmpty() === false ? $this->corsSettings->toRequestData() : null,
            'key_name' => $this->keyName,
            'key_permission' => $this->keyPermission,
        ]);
    }
}
