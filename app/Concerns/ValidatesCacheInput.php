<?php

namespace App\Concerns;

trait ValidatesCacheInput
{
    protected function validateCacheName(string $value): ?string
    {
        return match (true) {
            strlen($value) < 2 => 'Name must be at least 2 characters',
            strlen($value) > 40 => 'Name must be 40 characters or fewer',
            ! preg_match('/^[a-z0-9_-]+$/', $value) => 'Name may only contain lowercase letters, numbers, dashes, and underscores',
            default => null,
        };
    }

    protected function validateHibernationTimeout(mixed $value): ?string
    {
        $minutes = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 60]]);

        return $minutes === false ? 'Enter a whole number of minutes from 1 to 60' : null;
    }
}
