<?php

namespace App\Dto\Transformers;

use App\Support\SensitiveValues;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

class MaskEnvironmentVariables implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): array
    {
        if (SensitiveValues::$reveal) {
            return $value;
        }

        return array_map(
            fn (array $item) => array_merge($item, [
                'value' => '*****',
            ]),
            $value,
        );
    }
}
