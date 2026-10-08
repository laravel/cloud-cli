<?php

namespace App\Client\Requests;

class UpdateVanityDomainRequestData extends RequestData
{
    public function __construct(
        public readonly string $environmentId,
        public readonly string $name,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
