<?php

use App\Support\Sparkline;

it('returns an empty string without values', function () {
    expect(Sparkline::make([]))->toBe('');
});

it('scales values between the lowest and highest tick', function () {
    expect(Sparkline::make([0, 50, 100]))->toBe('▁▅█');
});

it('draws a flat line when every value is the same', function () {
    expect(Sparkline::make([7, 7, 7, 7]))->toBe('▁▁▁▁');
});

it('averages long series down to the requested width', function () {
    $sparkline = Sparkline::make(range(1, 500), width: 20);

    expect(mb_strlen($sparkline))->toBe(20);
    expect($sparkline)->toStartWith('▁');
    expect($sparkline)->toEndWith('█');
});

it('ignores non numeric values', function () {
    expect(Sparkline::make([0, null, 100, 'nope']))->toBe('▁█');
});
