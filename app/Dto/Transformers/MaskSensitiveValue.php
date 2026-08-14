<?php

namespace App\Dto\Transformers;

use App\Support\SensitiveValues;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

class MaskSensitiveValue implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): mixed
    {
        if (SensitiveValues::$reveal || $value === null) {
            return $value;
        }

        return SensitiveValues::MASK;
    }
}
