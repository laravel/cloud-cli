<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class CacheTypeSize extends Data
{
    public function __construct(
        public readonly string $value,
        public readonly string $label,
        public readonly bool $supportsHibernation = false,
    ) {
        //
    }
}
