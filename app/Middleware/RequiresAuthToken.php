<?php

namespace App\Middleware;

use App\Concerns\HasAClient;
use App\Contracts\NoAuthRequired;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

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

        try {
            $this->ensureApiTokenExists();
        } catch (RuntimeException $e) {
            fwrite(STDERR, json_encode(['error' => true, 'message' => $e->getMessage()]).PHP_EOL);

            return;
        }

        return $next();
    }
}
