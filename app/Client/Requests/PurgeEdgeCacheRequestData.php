<?php

namespace App\Client\Requests;

class PurgeEdgeCacheRequestData extends RequestData
{
    public function __construct(
        public readonly string $environmentId,
        public readonly ?string $path = null,
        public readonly ?string $prefix = null,
        public readonly ?string $tag = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'path' => $this->path,
            'prefix' => $this->prefix,
            'tag' => $this->tag,
        ]);
    }
}
