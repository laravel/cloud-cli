<?php

namespace App;

class Cloud
{
    public const API_TOKEN_ENV_VAR = 'LARAVEL_CLOUD_TOKEN';

    public const DEFAULT_URL = 'https://cloud.laravel.com';

    public static function baseUrl(): string
    {
        $baseUrl = config('cloud.base_url');

        if (str_starts_with($baseUrl, 'https://') || str_starts_with($baseUrl, 'http://')) {
            return $baseUrl;
        }

        return 'https://'.$baseUrl;
    }

    public static function apiTokenFromEnvironment(): ?string
    {
        $token = config('cloud.api_token');

        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        return trim($token);
    }
}
