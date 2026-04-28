<?php

namespace App\Client\Requests;

use SensitiveParameter;

class ReplaceEnvironmentVariablesRequestData extends RequestData
{
    public function __construct(
        public readonly string $environmentId,
        #[SensitiveParameter]
        public readonly ?string $content = null,
        /** @var list<array{key: string, value: string}>|null */
        #[SensitiveParameter]
        public readonly ?array $variables = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'variables' => $this->variables,
            'content' => $this->content,
        ]);
    }
}
