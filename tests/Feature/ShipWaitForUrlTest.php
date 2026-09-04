<?php

use App\Commands\Ship;
use App\Dto\Environment;
use Carbon\CarbonInterval;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Carbon::setTestNow('2026-01-01 00:00:00');

    // The poll bounds itself against the clock, so faked sleeps have to move it.
    Sleep::fake(syncWithCarbon: true);
});

function shipEnvironment(): Environment
{
    return Environment::createFromResponse(['data' => createEnvironmentResponse()]);
}

function waitForUrl(int $timeoutSeconds): bool
{
    $ship = app(Ship::class);

    return (new ReflectionMethod($ship, 'waitForUrlToBeReady'))->invoke($ship, shipEnvironment(), $timeoutSeconds);
}

it('returns true as soon as the site responds successfully', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push('', 404)
            ->push('', 404)
            ->push('ok', 200),
    ]);

    expect(waitForUrl(120))->toBeTrue();

    Http::assertSentCount(3);
    Sleep::assertSleptTimes(2);
});

it('waits two seconds between attempts', function () {
    Http::fake(['*' => Http::sequence()->push('', 404)->push('ok', 200)]);

    waitForUrl(120);

    Sleep::assertSequence([Sleep::for(2)->seconds()]);
});

it('stops polling and returns false once the deadline passes', function () {
    Http::fake(['*' => Http::response('', 404)]);

    expect(waitForUrl(10))->toBeFalse();

    Http::assertSentCount(5);
});

it('bounds the total wait even when every request times out', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        Sleep::for(CarbonInterval::seconds(5));

        throw new ConnectionException('Connection timed out');
    });

    expect(waitForUrl(30))->toBeFalse();

    // Five seconds burnt per attempt plus two between them, so a 30 second budget buys
    // five attempts — not five times the budget.
    expect($attempts)->toBe(5)
        ->and(Carbon::now()->toDateTimeString())->toBe('2026-01-01 00:00:35');
});

it('returns false immediately on a server error', function () {
    Http::fake(['*' => Http::response('', 500)]);

    expect(waitForUrl(120))->toBeFalse();

    Http::assertSentCount(1);
    Sleep::assertSleptTimes(0);
});

it('treats a connection failure as not ready and keeps polling', function () {
    Http::fake([
        '*' => Http::sequence()
            ->pushFailedConnection()
            ->push('ok', 200),
    ]);

    expect(waitForUrl(120))->toBeTrue();

    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

it('does not throw when every attempt fails to connect', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('DNS lookup failed')]);

    expect(waitForUrl(10))->toBeFalse();
});

it('treats an auth challenge on the root path as ready', function () {
    Http::fake(['*' => Http::response('', 401)]);

    expect(waitForUrl(120))->toBeTrue();

    Http::assertSentCount(1);
    Sleep::assertSleptTimes(0);
});

it('treats a forbidden root path as ready', function () {
    Http::fake(['*' => Http::response('', 403)]);

    expect(waitForUrl(120))->toBeTrue();

    Http::assertSentCount(1);
});
