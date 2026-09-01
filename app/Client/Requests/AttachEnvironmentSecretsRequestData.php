<?php

namespace App\Client\Requests;

class AttachEnvironmentSecretsRequestData extends RequestData
{
    /**
     * @param  list<string>  $secrets
     */
    public function __construct(
        public readonly string $environmentId,
        public readonly array $secrets,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return [
            'secrets' => $this->secrets,
        ];
    }
}
