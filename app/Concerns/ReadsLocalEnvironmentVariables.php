<?php

namespace App\Concerns;

use Dotenv\Dotenv;
use Illuminate\Support\Arr;
use Throwable;

trait ReadsLocalEnvironmentVariables
{
    /**
     * Keys every Laravel app has are left out unless asked for, since Cloud manages its own values for them.
     *
     * @return array<string, string|null>
     */
    protected function localEnvironmentVariables(string $directory, bool $includeLaravelKeys = false): array
    {
        $envPath = $directory.'/.env';

        if (! file_exists($envPath)) {
            return [];
        }

        try {
            $variables = Dotenv::parse(file_get_contents($envPath));
        } catch (Throwable) {
            return [];
        }

        return $includeLaravelKeys ? $variables : Arr::except($variables, config('env.laravel'));
    }
}
