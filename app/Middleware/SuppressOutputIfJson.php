<?php

namespace App\Middleware;

use App\Contracts\NoAuthRequired;
use App\Prompts\Renderer;
use App\Prompts\SuppressedOutput;
use App\Support\DetectsNonInteractiveEnvironments;
use Illuminate\Support\Facades\Artisan;
use Laravel\Prompts\Prompt;

class SuppressOutputIfJson implements CommandMiddleware
{
    use DetectsNonInteractiveEnvironments;

    public function handle($command, callable $next)
    {
        if (in_array($command, ['list', 'help'])) {
            Renderer::$suppressOutput = true;
            Prompt::setOutput(new SuppressedOutput);

            return $next();
        }

        $commandClass = Artisan::all()[$command] ?? null;

        if ($commandClass === null || $commandClass instanceof NoAuthRequired) {
            return $next();
        }

        $args = $_SERVER['argv'] ?? [];

        Renderer::$suppressOutput = collect($args)->intersect(['--json', '--no-interaction'])->isNotEmpty() || $this->isNonInteractiveEnvironment();

        if (Renderer::$suppressOutput) {
            Prompt::setOutput(new SuppressedOutput);
        }

        return $next();
    }
}
