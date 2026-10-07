<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class InstanceProbe extends Data
{
    public function __construct(
        public readonly ?string $path = null,
        public readonly ?int $port = null,
        public readonly ?int $delay = null,
        public readonly ?int $interval = null,
        public readonly ?int $timeout = null,
        public readonly ?int $tries = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return array_filter($this->toArray(), fn ($value) => $value !== null);
    }
}
