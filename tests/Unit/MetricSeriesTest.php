<?php

use App\Dto\MetricSeries;

it('reads a single series with its stats', function () {
    $series = MetricSeries::fromApiResponse([
        'data' => [
            ['x' => '2026-08-14T12:00:00.000000Z', 'y' => 1],
            ['x' => '2026-08-14T12:05:00.000000Z', 'y' => 3],
        ],
        'average' => 2,
    ]);

    expect($series->average)->toBe(2.0);
    expect($series->total)->toBeNull();
    expect($series->values())->toBe([1.0, 3.0]);
    expect($series->points[0]->at->toDateTimeString())->toBe('2026-08-14 12:00:00');
});

it('has no data when the series is empty', function () {
    expect(MetricSeries::fromApiResponse([])->hasData())->toBeFalse();
    expect(MetricSeries::fromApiResponse(['data' => []])->hasData())->toBeFalse();
});

it('reads a list of series keyed by name', function () {
    $series = MetricSeries::listFromApiResponse([
        'replica-1' => ['data' => [['x' => '2026-08-14T12:00:00.000000Z', 'y' => 1]], 'average' => 1],
        'replica-2' => ['data' => [['x' => '2026-08-14T12:00:00.000000Z', 'y' => 2]], 'average' => 2],
    ]);

    expect($series)->toHaveCount(2);
    expect($series[0]->name)->toBe('replica-1');
    expect($series[1]->values())->toBe([2.0]);
});

it('splits a labelled series into one series per label', function () {
    $series = MetricSeries::listFromApiResponse([
        'labels' => ['replica-1', 'replica-2'],
        'average' => [1, 2],
        'max' => 4,
        'data' => [
            ['x' => '2026-08-14T12:00:00.000000Z', 'y' => [1, 2]],
            ['x' => '2026-08-14T12:05:00.000000Z', 'y' => [3, 4]],
        ],
    ]);

    expect($series)->toHaveCount(2);
    expect($series[0]->name)->toBe('replica-1');
    expect($series[0]->average)->toBe(1.0);
    expect($series[0]->max)->toBe(4.0);
    expect($series[0]->values())->toBe([1.0, 3.0]);
    expect($series[1]->values())->toBe([2.0, 4.0]);
});

it('returns no series when the metric is empty or failed', function () {
    expect(MetricSeries::listFromApiResponse([]))->toBe([]);
    expect(MetricSeries::listFromApiResponse(null))->toBe([]);
    expect(MetricSeries::listFromApiResponse(['error' => true]))->toBe([]);
});
