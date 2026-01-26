<?php

namespace App\Support;

use App\Dto\ValidationErrors;
use RuntimeException;

class ValueResolver
{
    protected $fromInputCallback = null;

    protected $nonInteractivelyCallback = null;

    protected ?ValidationErrors $errors = null;

    protected $shouldPromptOnce = false;

    protected ?string $key = null;

    public function __construct(
        protected string $argumentName,
        protected bool $isInteractive,
        protected mixed $value = null,
        protected string $resolveFromType = 'argument',
    ) {
        //
    }

    public function paramKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function key(): string
    {
        return $this->key ?? $this->argumentName;
    }

    public function fromInput(callable $input): self
    {
        $this->fromInputCallback = $input;

        return $this;
    }

    public function shouldPromptOnce(): self
    {
        $this->shouldPromptOnce = true;

        return $this;
    }

    public function nonInteractively(callable $callback): self
    {
        $this->nonInteractivelyCallback = $callback;

        return $this;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function retrieve(): mixed
    {
        return $this->value = $this->retrieveValue();
    }

    protected function retrieveValue(): mixed
    {
        $this->errors ??= new ValidationErrors;

        if ($this->shouldPromptOnce) {
            $this->shouldPromptOnce = false;

            return ($this->fromInputCallback)($this->value);
        }

        if ($this->value !== null && ! $this->errors->has($this->key())) {
            return $this->value;
        }

        if ($this->isInteractive && $this->fromInputCallback && ($this->value === null || $this->errors->has($this->key()))) {
            return ($this->fromInputCallback)($this->value);
        }

        if ($this->value !== null) {
            return $this->value;
        }

        if ($this->isInteractive) {
            return null;
        }

        if ($this->nonInteractivelyCallback && $result = ($this->nonInteractivelyCallback)($this->value)) {
            return $result;
        }

        $message = match ($this->resolveFromType) {
            'option' => "{$this->argumentName} is required. Provide --{$this->argumentName} option.",
            default => "{$this->argumentName} is required. Provide {$this->argumentName} argument.",
        };

        throw new RuntimeException($message);
    }

    public function errors(ValidationErrors $errors): self
    {
        $this->errors = $errors;

        return $this;
    }
}
