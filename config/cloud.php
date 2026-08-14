<?php

/*
|--------------------------------------------------------------------------
| Laravel Cloud API Location
|--------------------------------------------------------------------------
|
| These values must NOT live in config/app.php: the `app:build` command
| evaluates config/app.php (and only that file) at build time and bakes
| the resulting array into the phar, which would freeze the env() calls
| below. In this file they are evaluated at runtime in built binaries.
|
*/

$defaultUrl = 'https://cloud.laravel.com';

return [

    'default_url' => $defaultUrl,

    'base_url' => trim(env('CLOUD_BASE_URL') ?: $defaultUrl, '/'),

];
