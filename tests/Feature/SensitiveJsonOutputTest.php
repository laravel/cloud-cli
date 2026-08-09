<?php

use App\Support\SensitiveValues;
use Illuminate\Support\Facades\Artisan;
use Tests\Fixtures\SensitiveJsonOutputTestCommand;

beforeEach(function () {
    Artisan::registerCommand(new SensitiveJsonOutputTestCommand);
});

afterEach(function () {
    SensitiveValues::$reveal = false;
});

it('masks environmentVariables in JSON output by default', function () {
    $exitCode = Artisan::call('test:sensitive-json', ['--no-interaction' => true]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);

    expect($payload['environmentVariables'])->toEqual([
        ['key' => 'APP_KEY', 'value' => '*****'],
        ['key' => 'STRIPE_SECRET', 'value' => '*****'],
    ]);
});

it('reveals environmentVariables when --show-sensitive is passed', function () {
    $exitCode = Artisan::call('test:sensitive-json', [
        '--no-interaction' => true,
        '--show-sensitive' => true,
    ]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);

    expect($payload['environmentVariables'])->toEqual([
        ['key' => 'APP_KEY', 'value' => 'base64:secret'],
        ['key' => 'STRIPE_SECRET', 'value' => 'sk_live_xyz'],
    ]);
});
