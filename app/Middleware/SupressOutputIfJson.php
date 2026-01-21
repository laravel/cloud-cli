<?php

namespace App\Middleware;

use App\Contracts\NoAuthRequired;
use App\Prompts\Renderer;
use Illuminate\Support\Facades\Artisan;

class SupressOutputIfJson implements CommandMiddleware
{
    public function handle($command, callable $next)
    {
        if ($command === 'list') {
            return $next();
        }

        $commandClass = Artisan::all()[$command] ?? null;

        if ($commandClass === null || $commandClass instanceof NoAuthRequired) {
            return $next();
        }

        Renderer::$suppressOutput = in_array('--json', $_SERVER['argv'] ?? []);

        return $next();
    }
}
