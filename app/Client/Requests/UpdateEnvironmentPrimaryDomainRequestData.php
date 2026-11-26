<?php

namespace App\Client\Requests;

class UpdateEnvironmentPrimaryDomainRequestData extends RequestData
{
    public function __construct(
        public readonly string $environmentId,
        public readonly string $domainId,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return [
            'domain_id' => $this->domainId,
        ];
    }
}
