<?php

namespace App\Support;

class Sparkline
{
    protected const TICKS = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];

    /**
     * @param  list<float>  $values
     */
    public static function make(array $values, int $width = 60): string
    {
        $values = array_map('floatval', array_values(array_filter($values, 'is_numeric')));

        if ($values === []) {
            return '';
        }

        $values = self::downsample($values, max(1, $width));

        $min = min($values);
        $range = max($values) - $min;

        return implode('', array_map(function (float $value) use ($min, $range) {
            if ($range <= 0) {
                return self::TICKS[0];
            }

            return self::TICKS[(int) round((($value - $min) / $range) * (count(self::TICKS) - 1))];
        }, $values));
    }

    /**
     * Average neighbouring values together so a long series still fits the width.
     *
     * @param  list<float>  $values
     * @return list<float>
     */
    protected static function downsample(array $values, int $width): array
    {
        $count = count($values);

        if ($count <= $width) {
            return $values;
        }

        $buckets = [];

        foreach ($values as $index => $value) {
            $buckets[(int) floor($index * $width / $count)][] = $value;
        }

        return array_values(array_map(fn (array $bucket) => array_sum($bucket) / count($bucket), $buckets));
    }
}
