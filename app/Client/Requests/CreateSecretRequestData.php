<?php

namespace App\Client\Requests;

use SensitiveParameter;

class CreateSecretRequestData extends RequestData
{
    public function __construct(
        public readonly string $keyPairId,
        public readonly string $key,
        #[SensitiveParameter]
        public readonly string $value,
        public readonly ?string $notes = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'key_pair_id' => $this->keyPairId,
            'key' => $this->key,
            'value' => $this->value,
            'notes' => $this->notes,
        ]);
    }
}
