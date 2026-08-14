<?php

namespace App\Enums;

enum EnvironmentStatus: string
{
    case DEPLOYING = 'deploying';
    case RUNNING = 'running';
    case HIBERNATING = 'hibernating';
    case STOPPED = 'stopped';

    public function label(): string
    {
        return match ($this) {
            self::HIBERNATING => 'scaled to zero',
            default => $this->value,
        };
    }
}
