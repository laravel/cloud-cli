<?php

namespace App;

class Cloud
{
    public static function baseUrl(): string
    {
        $baseUrl = config('cloud.base_url');

        if (str_starts_with($baseUrl, 'https://') || str_starts_with($baseUrl, 'http://')) {
            return $baseUrl;
        }

        return 'https://'.$baseUrl;
    }
}
