<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class ConfigSchema extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $required,
        public readonly ?bool $nullable = null,
        public readonly ?string $description = null,
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?array $enum = null,
        public readonly ?string $example = null,
    ) {
        //
    }
}
