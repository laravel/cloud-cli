<?php

namespace App\Middleware;

use App\Contracts\NoAuthRequired;
use App\Prompts\Renderer;
use Illuminate\Support\Facades\Artisan;

class SuppressOutputIfJson implements CommandMiddleware
{
    public function handle($command, callable $next)
    {
        if (in_array($command, ['list', 'help'])) {
            Renderer::$suppressOutput = true;

            return $next();
        }

        $commandClass = Artisan::all()[$command] ?? null;

        if ($commandClass === null || $commandClass instanceof NoAuthRequired) {
            return $next();
        }

        $args = $_SERVER['argv'] ?? [];

        Renderer::$suppressOutput = collect($args)->intersect(['--json', '--no-interaction'])->isNotEmpty();

        return $next();
    }
}
