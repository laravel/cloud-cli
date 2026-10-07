<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class EnvironmentResend extends Data
{
    public function __construct(
        public readonly string $fromAddress,
        public readonly string $fromName,
        public readonly string $keyName,
    ) {
        //
    }

    public static function createFromResponse(array $resend): self
    {
        return new self(
            fromAddress: $resend['from_address'],
            fromName: $resend['from_name'],
            keyName: $resend['key_name'],
        );
    }

    public function sender(): string
    {
        return "{$this->fromName} <{$this->fromAddress}>";
    }
}
