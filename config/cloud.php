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

use App\Cloud;

return [

    'default_url' => Cloud::DEFAULT_URL,

    'base_url' => rtrim(env('CLOUD_BASE_URL') ?: Cloud::DEFAULT_URL, '/'),

    'api_token' => env(Cloud::API_TOKEN_ENV_VAR),

];
