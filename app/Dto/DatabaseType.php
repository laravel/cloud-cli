<?php

namespace App\Dto;

class DatabaseType
{
    public function __construct(
        public readonly string $type,
        public readonly string $label,
        public readonly array $regions,
        public readonly array $configSchema,
    ) {
        //
    }

    public static function fromApiResponse(array $response, ?array $item = null): self
    {
        $data = $item ?? $response['data'] ?? [];

        return new self(
            type: $data['type'],
            label: $data['label'],
            regions: $data['regions'] ?? [],
            configSchema: array_map(
                fn (array $schema) => ConfigSchema::fromApiResponse($schema),
                $data['config_schema'] ?? []
            ),
        );
    }
}
