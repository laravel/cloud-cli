<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class MetricPoint extends Data
{
    public function __construct(
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $at,
        /** Null when the instance reported no sample for the interval, which is not zero usage. */
        public readonly ?float $value,
    ) {
        //
    }

    public static function fromApiResponse(array $point, int $index = 0): self
    {
        $value = $point['y'] ?? null;

        if (is_array($value)) {
            $value = $value[$index] ?? null;
        }

        return new self(
            at: isset($point['x']) ? CarbonImmutable::parse($point['x']) : null,
            value: is_numeric($value) ? (float) $value : null,
        );
    }
}
