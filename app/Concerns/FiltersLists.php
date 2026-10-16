<?php

namespace App\Concerns;

use BackedEnum;

trait FiltersLists
{
    /**
     * @param  class-string<BackedEnum>|list<string>|null  $allowed
     */
    protected function filterOption(string $option, string|array|null $allowed = null): ?string
    {
        $value = $this->option($option);

        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($allowed)) {
            $allowed = array_map(fn (BackedEnum $case) => (string) $case->value, $allowed::cases());
        }

        if ($allowed !== null && ! in_array($value, $allowed, true)) {
            $this->failAndExit("Invalid --{$option} value '{$value}'. Must be one of: ".implode(', ', $allowed).'.');
        }

        return $value;
    }
}
