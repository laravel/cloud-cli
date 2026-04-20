<?php

namespace App\Middleware\Concerns;

trait SkipsInternalCommands
{
    /**
     * Framework/tooling commands that user-flow middleware should skip.
     */
    protected function isInternalCommand(mixed $command): bool
    {
        $name = is_string($command) ? $command : $command?->getName();

        return in_array($name, [
            'list',
            'help',
            'app:build',
            '_complete',
            'completion',
        ], true);
    }
}
