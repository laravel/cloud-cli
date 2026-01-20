<?php

namespace App\Middleware;

use Illuminate\Console\Command;

class ExampleMiddleware implements CommandMiddleware
{
    /**
     * Handle the command before it's executed.
     *
     * @return mixed
     */
    public function handle(Command $command, callable $next)
    {
        // Perform any checks or operations before the command runs
        // For example: logging, validation, authentication, etc.

        // Example: Log command execution
        // \Log::info("Executing command: {$command->getName()}");

        // Example: Check if command should be allowed
        // if ($command->getName() === 'some:command' && !$this->isAllowed()) {
        //     $command->error('Command execution is not allowed');
        //     throw new \RuntimeException('Command execution stopped');
        // }

        // Continue to the next middleware/command
        return $next();
    }
}
