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
        /** @var list<string> The engine versions a cluster may be created with. */
        public readonly array $versions = [],
    ) {
        //
    }

    /**
     * Get the newest version a cluster of this type may be created with.
     */
    public function latestVersion(): ?string
    {
        if ($this->versions === []) {
            return null;
        }

        $versions = $this->versions;

        usort($versions, 'version_compare');

        return end($versions);
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];

        return self::from([
            'type' => $data['type'],
            'label' => $data['label'],
            'regions' => $data['regions'] ?? [],
            'versions' => array_values(array_map('strval', $data['versions'] ?? [])),
            'configSchema' => collect($data['config_schema'] ?? [])->map(fn (array $schema) => ConfigSchema::from($schema)->toArray())->toArray(),
        ]);
    }
}
