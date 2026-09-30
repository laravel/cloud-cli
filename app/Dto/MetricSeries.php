<?php

namespace App\Dto;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class MetricSeries extends Data
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?float $average = null,
        public readonly ?float $total = null,
        public readonly ?float $current = null,
        public readonly ?float $min = null,
        public readonly ?float $max = null,
        #[DataCollectionOf(MetricPoint::class)]
        public readonly array $points = [],
    ) {
        //
    }

    public static function fromApiResponse(mixed $series, ?string $name = null, int $index = 0): self
    {
        if (! is_array($series)) {
            return new self(name: $name);
        }

        return new self(
            name: $name,
            average: self::stat($series, 'average', $index),
            total: self::stat($series, 'total', $index),
            current: self::stat($series, 'current', $index),
            min: self::stat($series, 'min', $index),
            max: self::stat($series, 'max', $index),
            points: array_map(
                fn (array $point) => MetricPoint::fromApiResponse($point, $index),
                array_values(array_filter($series['data'] ?? [], 'is_array')),
            ),
        );
    }

    /**
     * A metric may hold several series at once: one per replica, keyed by name, or
     * one per label with the values zipped into each point.
     *
     * @return list<self>
     */
    public static function listFromApiResponse(mixed $series): array
    {
        // The API sends `{"error": true}` in place of a metric it failed to collect.
        if (! is_array($series) || $series === [] || isset($series['error'])) {
            return [];
        }

        if (array_key_exists('data', $series)) {
            $labels = array_values(array_filter($series['labels'] ?? [], 'is_string'));

            if ($labels === []) {
                return [self::fromApiResponse($series)];
            }

            return array_map(
                fn (string $label, int $index) => self::fromApiResponse($series, $label, $index),
                $labels,
                array_keys($labels),
            );
        }

        return collect($series)
            ->map(fn ($item, $key) => self::fromApiResponse($item, is_string($key) ? $key : null))
            ->values()
            ->all();
    }

    /**
     * @return list<float>
     */
    public function values(): array
    {
        return array_map(fn (MetricPoint $point) => $point->value, $this->points);
    }

    public function hasData(): bool
    {
        return $this->points !== [];
    }

    protected static function stat(array $series, string $key, int $index): ?float
    {
        $value = $series[$key] ?? null;

        if (is_array($value)) {
            $value = $value[$index] ?? null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
