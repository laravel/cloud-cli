<?php

namespace App\Client\Requests;

class DeleteEnvironmentVariablesRequestData extends RequestData
{
    public function __construct(
        public readonly string $environmentId,
        /** @var list<string> */
        public readonly array $keys,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return [
            'keys' => $this->keys,
        ];
    }
}
