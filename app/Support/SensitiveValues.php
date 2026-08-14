<?php

namespace App\Support;

class SensitiveValues
{
    public const MASK = '*****';

    public static bool $reveal = false;

    public static function isSensitiveKey(string $key): bool
    {
        return (bool) preg_match('/pass|secret|token|credential|private|dsn|url|uri/i', $key);
    }
}
