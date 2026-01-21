<?php

namespace App\Concerns;

trait MightWantJson
{
    protected function wantsJson(): bool
    {
        return $this->hasOption('json') && $this->option('json');
    }

    protected function outputJsonIfWanted(mixed $data): void
    {
        if ($this->wantsJson()) {
            $this->line($data->toJson());

            exit(0);
        }
    }
}
