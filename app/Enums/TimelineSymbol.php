<?php

namespace App\Enums;

enum TimelineSymbol: string
{
    case DOT = '•';
    case LINE = "\e[2m│\e[22m";
    case PENDING = '◆';
    case SUCCESS = '✔';
    case FAILURE = '✘';
    case WARNING = '▲';
    case CIRCLE = '●';
    case GREATER_THAN = '>';

    public function color(): string
    {
        return match ($this) {
            self::DOT, self::CIRCLE, self::GREATER_THAN => 'cyan',
            self::LINE => 'gray',
            self::PENDING, self::WARNING => 'yellow',
            self::SUCCESS => 'green',
            self::FAILURE => 'red',
        };
    }
}
