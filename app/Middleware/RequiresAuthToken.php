<?php

namespace App\Middleware;

use App\Concerns\HasAClient;
use App\Contracts\NoAuthRequired;
use Illuminate\Support\Facades\Artisan;

class RequiresAuthToken implements CommandMiddleware
{
    use HasAClient;

    public function handle($command, callable $next)
    {
        if (in_array($command, ['list', 'help', 'app:build', '_complete', 'completion'])) {
            return $next();
        }

        $commandClass = Artisan::all()[$command] ?? null;

        if ($commandClass === null || $commandClass instanceof NoAuthRequired) {
            return $next();
        }

        $this->ensureApiTokenExists();

        return $next();
    }
}
