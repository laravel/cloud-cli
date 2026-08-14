<?php

use App\Support\SensitiveValues;
use Illuminate\Support\Facades\Artisan;
use Tests\Fixtures\SensitiveConnectionJsonTestCommand;
use Tests\Fixtures\SensitiveJsonOutputTestCommand;

beforeEach(function () {
    Artisan::registerCommand(new SensitiveJsonOutputTestCommand);
    Artisan::registerCommand(new SensitiveConnectionJsonTestCommand);
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

it('masks connection credentials and secrets in JSON output by default', function () {
    $exitCode = Artisan::call('test:sensitive-connection-json', ['--no-interaction' => true]);

    expect($exitCode)->toBe(0);

    $output = Artisan::output();

    expect($output)
        ->not->toContain('cache-password')
        ->not->toContain('db-password')
        ->not->toContain('bucket-secret')
        ->not->toContain('ws-secret');

    $payload = json_decode($output, true);

    expect($payload['cache']['connection'])->toEqual([
        'hostname' => 'cache.example.com',
        'port' => 6379,
        'protocol' => 'redis',
        'username' => 'default',
        'password' => '*****',
        'url' => '*****',
    ]);

    expect($payload['databaseCluster']['connection'])->toEqual([
        'host' => 'db.example.com',
        'port' => 5432,
        'username' => 'forge',
        'password' => '*****',
        'dsn' => '*****',
    ]);

    expect($payload['bucketKey']['accessKeyId'])->toBe('AKIAEXAMPLE');
    expect($payload['bucketKey']['secretAccessKey'])->toBe('*****');

    expect($payload['websocketApplication']['key'])->toBe('ws-key');
    expect($payload['websocketApplication']['secret'])->toBe('*****');
});

it('reveals connection credentials and secrets when --show-sensitive is passed', function () {
    $exitCode = Artisan::call('test:sensitive-connection-json', [
        '--no-interaction' => true,
        '--show-sensitive' => true,
    ]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);

    expect($payload['cache']['connection']['password'])->toBe('cache-password');
    expect($payload['cache']['connection']['url'])->toBe('rediss://default:cache-password@cache.example.com:6379');
    expect($payload['databaseCluster']['connection']['password'])->toBe('db-password');
    expect($payload['bucketKey']['secretAccessKey'])->toBe('bucket-secret');
    expect($payload['websocketApplication']['secret'])->toBe('ws-secret');
});
