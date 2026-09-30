<?php

namespace App\Concerns;

use App\Dto\MetricSeries;
use App\Enums\MetricPeriod;
use App\Support\Formatter;
use App\Support\Sparkline;
use Closure;
use Laravel\Prompts\Prompt;

trait RendersMetrics
{
    protected function metricPeriod(): string
    {
        $period = (string) $this->option('period');

        if (MetricPeriod::tryFrom($period) === null) {
            $this->failAndExit("Invalid --period value '{$period}'. Must be one of: ".implode(', ', MetricPeriod::values()).'.');
        }

        return $period;
    }

    /**
     * @param  array<string, array>  $rows
     */
    protected function renderMetrics(?string $period, ?string $timeRange, array $rows): void
    {
        dataList([
            'Period' => [[$period, $timeRange]],
            ...$rows,
        ]);
    }

    /**
     * Rows for a metric the API splits into several series, one row per series.
     *
     * @param  list<MetricSeries>  $series
     * @param  array<string, string>  $stats
     * @return array<string, array>
     */
    protected function seriesListRows(string $label, array $series, Closure $format, array $stats = []): array
    {
        if ($series === []) {
            return [$label => [['No data']]];
        }

        $rows = [];

        foreach ($series as $index => $item) {
            $name = $item->name ?? (count($series) > 1 ? '#'.($index + 1) : null);

            $rows[$name === null ? $label : "{$label} ({$name})"] = $this->seriesLines($item, $format, $stats);
        }

        return $rows;
    }

    /**
     * A headline built from the series' stats, then a sparkline labelled with
     * where the series started and ended.
     *
     * @param  array<string, string>  $stats  Stat property => label, in display order.
     */
    protected function seriesLines(MetricSeries $series, Closure $format, array $stats = []): array
    {
        if (! $series->hasData()) {
            return [['No data']];
        }

        $summary = collect($stats)
            ->filter(fn (string $label, string $stat) => $series->{$stat} !== null)
            ->map(fn (string $label, string $stat) => $format($series->{$stat}).' '.$label)
            ->values();

        $values = $series->values();

        return array_values(array_filter([
            $summary->isEmpty() ? null : [$summary->first(), $summary->slice(1)->implode(' · ') ?: null],
            [
                Sparkline::make($values, $this->sparklineWidth()),
                $format($values[0]).' → '.$format($values[count($values) - 1]),
            ],
        ]));
    }

    protected function sparklineWidth(): int
    {
        return max(20, min(60, Prompt::terminal()->cols() - 30));
    }

    protected function formatPercent(): Closure
    {
        return fn (float $value) => number_format($value * 100, 2).'%';
    }

    protected function formatBytes(): Closure
    {
        return fn (float $value) => Formatter::bytes((int) $value);
    }

    protected function formatCount(): Closure
    {
        return fn (float $value) => number_format($value);
    }

    protected function formatDecimal(int $precision = 2): Closure
    {
        return fn (float $value) => number_format($value, $precision);
    }
}
