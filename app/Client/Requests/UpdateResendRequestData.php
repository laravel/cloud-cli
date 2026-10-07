<?php

namespace App\Client\Requests;

class UpdateResendRequestData extends RequestData
{
    public function __construct(
        public readonly string $environmentId,
        public readonly string $fromAddress,
        public readonly string $fromName,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return [
            'from_address' => $this->fromAddress,
            'from_name' => $this->fromName,
        ];
    }
}
