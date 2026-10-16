<?php

namespace App\Dto;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class CacheType extends Data
{
    public function __construct(
        public readonly string $type,
        public readonly string $label,
        public readonly array $regions,
        #[DataCollectionOf(CacheTypeSize::class)]
        public readonly array $sizes,
        public readonly bool $supportsAutoUpgrade,
        public readonly bool $supportsEvictionPolicy = false,
        public readonly bool $supportsHibernation = false,
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];

        return self::from([
            'type' => $data['type'] ?? '',
            'label' => $data['label'] ?? '',
            'regions' => $data['regions'] ?? [],
            'sizes' => array_map(fn (array $size) => [
                'value' => $size['value'] ?? '',
                'label' => $size['label'] ?? '',
                'supportsHibernation' => $size['supports_hibernation'] ?? false,
            ], $data['sizes'] ?? []),
            'supportsAutoUpgrade' => $data['supports_auto_upgrade'] ?? false,
            'supportsEvictionPolicy' => $data['supports_eviction_policy'] ?? false,
            'supportsHibernation' => $data['supports_hibernation'] ?? false,
        ]);
    }

    public function supportsHibernationFor(?string $size): bool
    {
        if (! $this->supportsHibernation) {
            return false;
        }

        foreach ($this->sizes as $typeSize) {
            if ($typeSize->value === $size) {
                return $typeSize->supportsHibernation;
            }
        }

        return false;
    }
}
