<?php

namespace App\Client\Requests;

class AttachResendRequestData extends RequestData
{
    public function __construct(
        public readonly string $environmentId,
        public readonly string $fromAddress,
        public readonly string $fromName,
        public readonly ?string $keyName = null,
        public readonly ?string $reuseKeyId = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'key_strategy' => $this->reuseKeyId !== null ? 'reuse' : 'create',
            'from_address' => $this->fromAddress,
            'from_name' => $this->fromName,
            'key_name' => $this->keyName,
            'reuse_key_id' => $this->reuseKeyId,
        ]);
    }
}
