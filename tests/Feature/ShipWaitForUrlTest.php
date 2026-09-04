<?php

use App\Commands\Ship;
use App\Dto\Environment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use ReflectionMethod;

beforeEach(function () {
    Sleep::fake();
});

function shipEnvironment(): Environment
{
    return Environment::createFromResponse(['data' => createEnvironmentResponse()]);
}

function waitForUrl(int $maxAttempts): bool
{
    $ship = app(Ship::class);

    return (new ReflectionMethod($ship, 'waitForUrlToBeReady'))->invoke($ship, shipEnvironment(), $maxAttempts);
}

it('returns true as soon as the site responds successfully', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push('', 404)
            ->push('', 404)
            ->push('ok', 200),
    ]);

    expect(waitForUrl(10))->toBeTrue();

    Http::assertSentCount(3);
    Sleep::assertSleptTimes(2);
});

it('stops polling and returns false when the attempt budget is exhausted', function () {
    Http::fake(['*' => Http::response('', 404)]);

    expect(waitForUrl(5))->toBeFalse();

    Http::assertSentCount(5);
    Sleep::assertSleptTimes(4);
});

it('returns false immediately on a server error', function () {
    Http::fake(['*' => Http::response('', 500)]);

    expect(waitForUrl(10))->toBeFalse();

    Http::assertSentCount(1);
    Sleep::assertSleptTimes(0);
});

it('treats a connection failure as not ready and keeps polling', function () {
    Http::fake([
        '*' => Http::sequence()
            ->pushFailedConnection()
            ->push('ok', 200),
    ]);

    expect(waitForUrl(10))->toBeTrue();

    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

it('does not throw when every attempt fails to connect', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('DNS lookup failed')]);

    expect(waitForUrl(3))->toBeFalse();

    Sleep::assertSleptTimes(2);
});

it('waits two seconds between attempts', function () {
    Http::fake(['*' => Http::sequence()->push('', 404)->push('ok', 200)]);

    waitForUrl(10);

    Sleep::assertSequence([Sleep::for(2)->seconds()]);
});

it('stops without retrying on an unfollowed redirect', function () {
    Http::fake(['*' => Http::response('', 302)]);

    expect(waitForUrl(10))->toBeFalse();

    Http::assertSentCount(1);
});
