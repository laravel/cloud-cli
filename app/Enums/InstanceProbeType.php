<?php

namespace App\Enums;

enum InstanceProbeType: string
{
    case STARTUP = 'startup';
    case READINESS = 'readiness';
    case LIVENESS = 'liveness';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
