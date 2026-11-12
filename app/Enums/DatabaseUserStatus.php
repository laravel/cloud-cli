<?php

namespace App\Enums;

enum DatabaseUserStatus: string
{
    case CREATING = 'creating';
    case AVAILABLE = 'available';
    case FAILED = 'failed';
    case DELETED = 'deleted';
}
