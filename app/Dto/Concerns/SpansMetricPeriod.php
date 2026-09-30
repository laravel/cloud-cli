<?php

namespace App\Dto\Concerns;

use App\Dto\MetricSeries;

trait SpansMetricPeriod
{
    /**
     * @return list<MetricSeries>
     */
    abstract public function allSeries(): array;

    public function timeRange(string $format = 'M j H:i'): ?string
    {
        $times = collect($this->allSeries())
            ->flatMap(fn (MetricSeries $series) => $series->points)
            ->pluck('at')
            ->filter()
            ->sort()
            ->values();

        if ($times->isEmpty()) {
            return null;
        }

        return $times->first()->format($format).' – '.$times->last()->format($format).' UTC';
    }

    /**
     * @return list<string>
     */
    protected static function availablePeriodsFrom(array $meta): array
    {
        return array_values(array_filter($meta['available_periods'] ?? [], 'is_string'));
    }
}
