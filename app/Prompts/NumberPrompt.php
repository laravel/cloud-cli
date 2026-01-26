<?php

namespace App\Prompts;

use Closure;
use Laravel\Prompts\Concerns\TypedValue;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

class NumberPrompt extends Prompt
{
    use TypedValue;

    /**
     * Create a new TextPrompt instance.
     */
    public function __construct(
        public string $label,
        public string $placeholder = '',
        public string $default = '',
        public bool|string $required = false,
        public mixed $validate = null,
        public string $hint = '',
        public ?Closure $transform = null,
        public ?int $min = null,
        public ?int $max = null,
    ) {
        $this->trackTypedValue($default);

        $this->min ??= PHP_INT_MIN;
        $this->max ??= PHP_INT_MAX;

        $originalValidate = $this->validate;

        $this->validate = function ($value) use ($originalValidate) {
            if (! is_numeric($value) && $this->required) {
                return 'Must be a number';
            }

            if (is_numeric($value)) {
                if ($value < $this->min) {
                    return 'Must be at least '.$this->min;
                }

                if ($value > $this->max) {
                    return 'Must be less than '.$this->max;
                }
            }

            if ($originalValidate) {
                return ($originalValidate)($value);
            }

            return null;
        };

        $this->on('key', function (string $key) {
            match ($key) {
                Key::UP, Key::UP_ARROW => $this->increaseValue(),
                Key::DOWN, Key::DOWN_ARROW => $this->decreaseValue(),
                default => null,
            };
        });
    }

    protected function increaseValue(): void
    {
        if (is_numeric($this->typedValue)) {
            $previousValueLength = mb_strlen($this->typedValue);

            $this->typedValue = min($this->max, (int) $this->typedValue + 1);

            if (mb_strlen($this->typedValue) > $previousValueLength) {
                $this->cursorPosition++;
            }
        }
    }

    protected function decreaseValue(): void
    {
        if (is_numeric($this->typedValue)) {
            $previousValueLength = mb_strlen($this->typedValue);

            $this->typedValue = max($this->min, (int) $this->typedValue - 1);

            if (mb_strlen($this->typedValue) < $previousValueLength) {
                $this->cursorPosition--;
            }
        }
    }

    /**
     * Get the entered value with a virtual cursor.
     */
    public function valueWithCursor(int $maxWidth): string
    {
        if ($this->value() === '') {
            return $this->dim($this->addCursor($this->placeholder, 0, $maxWidth));
        }

        return $this->addCursor($this->value(), $this->cursorPosition, $maxWidth);
    }
}
