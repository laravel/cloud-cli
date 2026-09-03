<?php

namespace App\Client\Requests;

class DeleteEnvironmentVariablesRequestData extends RequestData
{
    /**
     * @param  list<string>  $keys
     */
    public function __construct(
        public readonly string $environmentId,
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
