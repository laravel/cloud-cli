<?php

use App\Dto\Application;
use App\Dto\Environment;
use App\Support\SensitiveValues;

afterEach(function () {
    SensitiveValues::$reveal = false;
});

function makeEnvironment(array $vars): Environment
{
    return Environment::from([
        'id' => 'env-1',
        'url' => 'https://example.com',
        'name' => 'production',
        'branch' => 'main',
        'status' => 'running',
        'instances' => null,
        'buildCommand' => null,
        'deployCommand' => null,
        'slug' => 'production',
        'statusEnum' => 'running',
        'createdFromAutomation' => false,
        'vanityDomain' => 'example.com',
        'phpMajorVersion' => '8.3',
        'environmentVariables' => $vars,
    ]);
}

it('masks environmentVariables values when serialized to array', function () {
    $env = makeEnvironment([
        ['key' => 'APP_KEY', 'value' => 'base64:secret'],
        ['key' => 'STRIPE_SECRET', 'value' => 'sk_live_xyz'],
    ]);

    expect($env->toArray()['environmentVariables'])->toEqual([
        ['key' => 'APP_KEY', 'value' => '*****'],
        ['key' => 'STRIPE_SECRET', 'value' => '*****'],
    ]);
});

it('reveals real values when SensitiveValues::$reveal is true', function () {
    SensitiveValues::$reveal = true;

    $env = makeEnvironment([
        ['key' => 'APP_KEY', 'value' => 'base64:secret'],
    ]);

    expect($env->toArray()['environmentVariables'])->toEqual([
        ['key' => 'APP_KEY', 'value' => 'base64:secret'],
    ]);
});

it('leaves the underlying property untouched so internal reads still see real values', function () {
    $env = makeEnvironment([
        ['key' => 'APP_KEY', 'value' => 'base64:secret'],
    ]);

    expect($env->environmentVariables)->toEqual([
        ['key' => 'APP_KEY', 'value' => 'base64:secret'],
    ]);
});

it('masks values inside environments nested under an Application DTO', function () {
    $app = Application::from([
        'id' => 'app-1',
        'name' => 'My App',
        'slug' => 'my-app',
        'region' => 'us-east-1',
        'environmentIds' => ['env-1'],
        'environments' => [
            makeEnvironment([
                ['key' => 'TOKEN', 'value' => 'super-secret'],
            ]),
        ],
    ]);

    expect($app->toArray()['environments'][0]['environmentVariables'][0])
        ->toEqual(['key' => 'TOKEN', 'value' => '*****']);
});

it('handles empty environmentVariables arrays', function () {
    $env = makeEnvironment([]);

    expect($env->toArray()['environmentVariables'])->toBe([]);
});
