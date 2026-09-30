<?php

use App\Concerns\RendersMetrics;
use App\Dto\MetricSeries;

function metricsRenderer(): object
{
    return new class
    {
        use RendersMetrics {
            seriesLines as public;
            seriesListRows as public;
            formatBytes as public;
            formatCount as public;
        }
    };
}

function labelledSeries(): array
{
    return MetricSeries::listFromApiResponse([
        'labels' => ['App', 'Worker'],
        'average' => [2, 4],
        'max' => 6,
        'data' => [
            ['x' => '2026-08-14T12:00:00.000000Z', 'y' => [1, 2]],
            ['x' => '2026-08-14T12:05:00.000000Z', 'y' => [3, 6]],
        ],
    ]);
}

it('summarizes stats in order, then the start and end values', function () {
    $renderer = metricsRenderer();

    [$summary, $trend] = $renderer->seriesLines(labelledSeries()[1], $renderer->formatCount(), [
        'average' => 'average',
        'max' => 'max',
        'total' => 'total',
    ]);

    expect($summary)->toBe(['4 average', '6 max']);
    expect($trend[1])->toBe('2 → 6');
});

it('shows no data for an empty series', function () {
    $renderer = metricsRenderer();

    expect($renderer->seriesLines(new MetricSeries, $renderer->formatCount(), ['average' => 'average']))
        ->toBe([['No data']]);
});

it('adds a row per labelled series', function () {
    $renderer = metricsRenderer();

    expect(array_keys($renderer->seriesListRows('CPU', labelledSeries(), $renderer->formatCount())))
        ->toBe(['CPU (App)', 'CPU (Worker)']);
});

it('numbers unnamed series when there are several', function () {
    $renderer = metricsRenderer();

    $series = [new MetricSeries(points: labelledSeries()[0]->points), new MetricSeries(points: labelledSeries()[1]->points)];

    expect(array_keys($renderer->seriesListRows('Lag', $series, $renderer->formatCount())))
        ->toBe(['Lag (#1)', 'Lag (#2)']);
});

it('keeps a single row when a metric has no series', function () {
    $renderer = metricsRenderer();

    expect($renderer->seriesListRows('Web Workers', [], $renderer->formatCount()))
        ->toBe(['Web Workers' => [['No data']]]);
});

it('formats bytes', function () {
    expect((metricsRenderer()->formatBytes())(2097152))->toBe('2 MB');
});
