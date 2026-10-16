<?php

namespace App\Enums;

enum FilesystemLocationHint: string
{
    case AUTOMATIC = 'automatic';
    case WNAM = 'wnam';
    case ENAM = 'enam';
    case WEUR = 'weur';
    case EEUR = 'eeur';
    case APAC = 'apac';
    case OC = 'oc';

    public function label(): string
    {
        return match ($this) {
            self::AUTOMATIC => 'Automatic',
            self::WNAM => 'Western North America',
            self::ENAM => 'Eastern North America',
            self::WEUR => 'Western Europe',
            self::EEUR => 'Eastern Europe',
            self::APAC => 'Asia-Pacific',
            self::OC => 'Oceania',
        };
    }
}
