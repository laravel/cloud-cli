<?php

namespace App\Dto;

class Environment
{
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly string $name,
        public readonly ?string $branch = null,
        public readonly ?string $status = null,
        public readonly array $instances = [],
    ) {
        //
    }

    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['attributes']['name'],
            url: str($data['attributes']['vanity_domain'])->start('https://'),
            branch: $data['attributes']['branch'] ?? null,
            status: $data['attributes']['status'],
            instances: array_column($data['relationships']['instances']['data'] ?? [], 'id'),
        );
    }
}
