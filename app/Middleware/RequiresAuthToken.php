<?php

namespace App\Middleware;

use App\Concerns\HasAClient;
use App\Contracts\NoAuthRequired;
use App\Middleware\Concerns\SkipsInternalCommands;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class RequiresAuthToken implements CommandMiddleware
{
    use HasAClient;
    use SkipsInternalCommands;

    public function handle($command, callable $next)
    {
        if ($this->isInternalCommand($command)) {
            return $next();
        }

        $commandClass = Artisan::all()[$command] ?? null;

        if ($commandClass === null || $commandClass instanceof NoAuthRequired) {
            return $next();
        }

        try {
            $this->ensureApiTokenExists();
        } catch (RuntimeException $e) {
            fwrite(STDERR, json_encode(['error' => true, 'message' => $e->getMessage()]).PHP_EOL);

            return;
        }

        return $next();
    }
}
