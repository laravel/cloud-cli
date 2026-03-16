<?php

namespace App\Concerns;

/**
 * Provides consistent error output formatting across commands and resolvers.
 *
 * When non-interactive (or JSON requested), errors are output as JSON.
 * When interactive, errors use Laravel Prompts' error() function.
 */
trait FormatsErrors
{
    /**
     * Format an error message as JSON for non-interactive output.
     */
    protected function formatErrorAsJson(string $message): string
    {
        return json_encode(['message' => $message, 'error' => true]);
    }
}
