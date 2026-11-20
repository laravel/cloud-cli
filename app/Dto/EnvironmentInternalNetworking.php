<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class EnvironmentInternalNetworking extends Data
{
    public function __construct(
        public readonly bool $enabled,
        public readonly ?string $hostname = null,
        public readonly ?string $accessLevel = null,
        /** @var list<string> */
        public readonly array $environmentIds = [],
    ) {
        //
    }

    public static function createFromResponse(array $internalNetworking): self
    {
        return new self(
            enabled: (bool) ($internalNetworking['enabled'] ?? false),
            hostname: $internalNetworking['hostname'] ?? null,
            accessLevel: $internalNetworking['access_level'] ?? null,
            environmentIds: $internalNetworking['environment_ids'] ?? [],
        );
    }
}
