<?php

namespace App\Middleware;

use Illuminate\Console\Command;

interface CommandMiddleware
{
    /**
     * Handle the command before it's executed.
     *
     * @return mixed
     */
    public function handle(Command $command, callable $next);
}
