<?php

namespace App\Prompts;

use Closure;
use Illuminate\Support\Collection;
use Laravel\Prompts\SelectPrompt;

class SelectWithContextPrompt extends SelectPrompt
{
    public array $context = [];

    /**
     * @param  array<string|int, string|array{string, string}>|Collection<string|int, string|array{string, string}>  $options
     */
    public function __construct(
        string $label,
        array|Collection $options,
        int|string|null $default = null,
        int $scroll = 5,
        mixed $validate = null,
        string $hint = '',
        bool|string $required = true,
        ?Closure $transform = null,
    ) {
        $newOptions = [];

        foreach ($options as $key => $value) {
            $newOptions[$key] = is_array($value) ? $value[0] : $value;
            $this->context[] = is_array($value) ? $value[1] : $key;
        }

        parent::__construct($label, $newOptions, $default, $scroll, $validate, $hint, $required, $transform);
    }

    public function context(): string
    {
        return $this->context[$this->highlighted] ?? '';
    }
}
