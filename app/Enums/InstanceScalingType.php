<?php

namespace App\Enums;

enum InstanceScalingType: string
{
    case NONE = 'none';
    case CUSTOM = 'custom';
    case AUTO = 'auto';
}
