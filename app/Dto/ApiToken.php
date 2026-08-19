<?php

namespace App\Dto;

use App\Dto\Transformers\MaskSensitiveSuffix;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

class ApiToken extends Data
{
    public function __construct(
        public readonly string $organization,
        #[WithTransformer(MaskSensitiveSuffix::class)]
        public readonly string $token,
        public readonly string $source,
    ) {
        //
    }
}
