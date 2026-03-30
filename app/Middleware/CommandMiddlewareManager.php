<?php

namespace App\Middleware;

use Illuminate\Console\Command;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

class CommandMiddlewareManager
{
    /**
     * The registered middleware.
     *
     * @var Collection<int, class-string<CommandMiddleware>>
     */
    protected Collection $middleware;

    public function __construct()
    {
        $this->middleware = collect();
    }

    /**
     * Register middleware to run before commands.
     *
     * @param  class-string<CommandMiddleware>|array<class-string<CommandMiddleware>>  $middleware
     */
    public function register(string|array $middleware): void
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];

        foreach ($middleware as $m) {
            $this->middleware->push($m);
        }
    }

    /**
     * Handle the Symfony console command event.
     */
    public function handleConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();

        if (! $command instanceof Command) {
            return;
        }

        $shouldContinue = false;

        $this->executeMiddleware($command, function () use (&$shouldContinue) {
            $shouldContinue = true;
        });

        if (! $shouldContinue) {
            $event->disableCommand();
        }
    }

    /**
     * Handle the Laravel command starting event.
     */
    public function handleCommandStarting(CommandStarting $event): void
    {
        $command = $event->command;

        if (! $command) {
            return;
        }

        $this->executeMiddleware($command, function () {
            // Continue with command execution
        });
    }

    /**
     * Execute all registered middleware.
     *
     * @return mixed
     */
    public function executeMiddleware(Command|string $command, callable $next)
    {
        if ($this->middleware->isEmpty()) {
            return $next();
        }

        $middleware = $this->middleware->reverse()->values();

        $pipeline = $middleware->reduce(function ($carry, $middlewareClass) use ($command) {
            return function () use ($carry, $middlewareClass, $command) {
                $middleware = app($middlewareClass);

                return $middleware->handle($command, $carry);
            };
        }, $next);

        return $pipeline();
    }

    /**
     * Get all registered middleware.
     *
     * @return Collection<int, class-string<CommandMiddleware>>
     */
    public function getMiddleware(): Collection
    {
        return $this->middleware;
    }
}
