<?php

namespace App\Enums;

enum FilesystemType: string
{
    case CLOUDFLARE_R2 = 'cloudflare_r2';
    case FAKE = 'fake';
}
