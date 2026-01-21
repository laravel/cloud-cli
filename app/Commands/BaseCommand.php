<?php

namespace App\Commands;

use App\Concerns\MightWantJson;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

abstract class BaseCommand extends Command
{
    use Colors;
    use MightWantJson;

    protected function intro(string $title, ?string $suffix = null): void
    {
        if ($this->wantsJson()) {
            return;
        }

        if ($suffix) {
            $title .= ': '.$suffix;
        }

        intro($title);
    }

    protected function outro(string $title): void
    {
        if ($this->wantsJson()) {
            return;
        }

        outro($title);
    }

    protected function ensureInteractive(string $message): void
    {
        if (! $this->isInteractive()) {
            throw new RuntimeException($message);
        }
    }

    protected function isInteractive(): bool
    {
        return stream_isatty(STDIN) && ! $this->wantsJson();
    }

    protected function outputErrorOrThrow(string $message): void
    {
        if ($this->isInteractive()) {
            error($message);
        } else {
            throw new RuntimeException($message);
        }
    }
}
