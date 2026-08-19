<?php

namespace App\Dto\Transformers;

use App\Support\SensitiveValues;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

class MaskSensitiveSuffix implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return SensitiveValues::maskWithSuffix($value);
    }
}
