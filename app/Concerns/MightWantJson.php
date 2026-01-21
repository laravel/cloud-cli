<?php

namespace App\Concerns;

trait MightWantJson
{
    protected function wantsJson(): bool
    {
        return $this->hasOption('json') && $this->option('json');
    }
}
