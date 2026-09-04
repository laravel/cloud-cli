<?php

namespace App\Client\Requests;

use App\Enums\SourceProvider;

class UpdateApplicationRequestData extends RequestData
{
    public function __construct(
        public readonly string $applicationId,
        public readonly ?string $name = null,
        public readonly ?string $slug = null,
        public readonly ?string $defaultEnvironmentId = null,
        public readonly ?string $repository = null,
        public readonly ?string $slackChannel = null,
        public readonly ?SourceProvider $sourceProvider = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'name' => $this->name,
            'slug' => $this->slug,
            'default_environment_id' => $this->defaultEnvironmentId,
            'repository' => $this->repository,
            'slack_channel' => $this->slackChannel,
            'source_control_provider_type' => $this->sourceProvider?->value,
        ]);
    }
}
