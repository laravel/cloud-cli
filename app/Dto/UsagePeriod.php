<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class UsagePeriod extends Data
{
    public function __construct(
        public readonly ?string $from,
        public readonly ?string $to,
    ) {
        //
    }

    public static function fromApiResponse(array $item): self
    {
        return new self(
            from: $item['from'] ?? null,
            to: $item['to'] ?? null,
        );
    }
}
