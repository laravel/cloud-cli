<?php

namespace App\Client\Requests;

class CodeExecutionRequestData extends RequestData
{
    public function __construct(
        public readonly string $environmentId,
        public readonly string $code,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return [
            'code' => $this->code,
        ];
    }
}
