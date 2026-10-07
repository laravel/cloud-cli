<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class ResendSendingKey extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $environmentCount,
    ) {
        //
    }

    public static function createFromResponse(array $key): self
    {
        return new self(
            id: $key['id'],
            name: $key['name'],
            environmentCount: $key['environment_count'],
        );
    }
}
