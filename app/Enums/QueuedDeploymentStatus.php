<?php

namespace App\Enums;

enum QueuedDeploymentStatus: string
{
    case PENDING = 'pending';
    case PROMOTED = 'promoted';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return str($this->name)->lower()->ucfirst()->toString();
    }
}
