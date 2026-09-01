<?php

namespace App\Client\Requests;

use SensitiveParameter;

class UpdateSecretRequestData extends RequestData
{
    public function __construct(
        public readonly string $secretId,
        public readonly string $key,
        #[SensitiveParameter]
        public readonly ?string $value = null,
        public readonly ?string $keyPairId = null,
        public readonly ?string $notes = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'key' => $this->key,
            'value' => $this->value,
            'key_pair_id' => $this->keyPairId,
            'notes' => $this->notes,
        ]);
    }
}
