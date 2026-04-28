<?php

namespace App\Client\Requests;

use SensitiveParameter;

class AddEnvironmentVariablesRequestData extends RequestData
{
    /**
     * @param  'append'|'set'  $method
     */
    public function __construct(
        public readonly string $environmentId,
        #[SensitiveParameter]
        public readonly array $variables,
        public readonly string $method = 'append',
    ) {
        //
    }

    public function toRequestData(): array
    {
        return [
            'method' => $this->method,
            'variables' => $this->variables,
        ];
    }
}
