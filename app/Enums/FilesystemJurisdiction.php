<?php

namespace App\Enums;

enum FilesystemJurisdiction: string
{
    case DEFAULT = 'default';
    case EU = 'eu';
    case US = 'us';

    public function label(): string
    {
        return match ($this) {
            self::DEFAULT => 'Automatic',
            self::EU => 'European Union',
            self::US => 'United States',
        };
    }
}
