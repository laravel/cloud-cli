<?php

namespace App\Client\Requests;

class UpdateApplicationRequestData extends RequestData
{
    public function __construct(
        public readonly string $applicationId,
        public readonly ?string $name = null,
        public readonly ?string $slug = null,
        public readonly ?string $defaultEnvironmentId = null,
        public readonly ?string $repository = null,
        public readonly ?string $slackChannel = null,
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
        ]);
    }
}
