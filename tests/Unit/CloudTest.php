<?php

use App\Cloud;

it('adds a scheme to the base URL when one is missing', function (string $baseUrl, string $expected) {
    config(['cloud.base_url' => $baseUrl]);

    expect(Cloud::baseUrl())->toBe($expected);
})->with([
    'no scheme' => ['cloud.laravel.test', 'https://cloud.laravel.test'],
    'https' => ['https://cloud.laravel.test', 'https://cloud.laravel.test'],
    'http' => ['http://cloud.laravel.test', 'http://cloud.laravel.test'],
    'no scheme with port' => ['cloud.laravel.test:8080', 'https://cloud.laravel.test:8080'],
    'default' => ['https://cloud.laravel.com', 'https://cloud.laravel.com'],
]);
