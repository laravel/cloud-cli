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

    /** Keeps the last few characters so tokens sharing an organization stay tellable apart. */
    public static function maskWithSuffix(string $value, int $suffix = 4): string
    {
        if (static::$reveal) {
            return $value;
        }

        if (mb_strlen($value) <= $suffix) {
            return static::MASK;
        }

        return static::MASK.mb_substr($value, -$suffix);
    }
}
