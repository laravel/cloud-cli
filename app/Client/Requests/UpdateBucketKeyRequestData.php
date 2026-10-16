<?php

namespace App\Client\Requests;

class UpdateBucketKeyRequestData extends RequestData
{
    public function __construct(
        public readonly string $filesystemKey,
        public readonly string $name,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'name' => $this->name,
        ]);
    }
}
