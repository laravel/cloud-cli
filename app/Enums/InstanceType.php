<?php

namespace App\Enums;

enum InstanceType: string
{
    case SERVICE = 'service';
    case MANAGED_QUEUE = 'managed_queue';
}
