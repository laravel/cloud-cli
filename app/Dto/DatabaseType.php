<?php

namespace App\Dto;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class DatabaseType extends Data
{
    public function __construct(
        public readonly string $type,
        public readonly string $label,
        public readonly array $regions,
        #[DataCollectionOf(ConfigSchema::class)]
        public readonly array $configSchema,
    ) {
        //
    }

    public static function fromJsonApi(array $response): self
    {
        $data = $response['data'] ?? [];

        return self::from([
            'type' => $data['type'],
            'label' => $data['label'],
            'regions' => $data['regions'] ?? [],
            'configSchema' => collect($data['config_schema'] ?? [])->map(fn (array $schema) => ConfigSchema::from($schema)->toArray())->toArray(),
        ]);
    }
}
