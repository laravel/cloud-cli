<?php

namespace App\Enums;

enum WebsocketServerStatus: string
{
    case CREATING = 'creating';
    case UPDATING = 'updating';
    case AVAILABLE = 'available';
    case STOPPED = 'stopped';
    case DELETING = 'deleting';
    case DELETED = 'deleted';
    case UNKNOWN = 'unknown';
}
