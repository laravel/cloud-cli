<?php

namespace App\Enums;

enum MetricPeriod: string
{
    case SIX_HOURS = '6h';
    case TWENTY_FOUR_HOURS = '24h';
    case THREE_DAYS = '3d';
    case SEVEN_DAYS = '7d';
    case THIRTY_DAYS = '30d';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
