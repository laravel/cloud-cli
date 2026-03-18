<?php

namespace App\Support;

use App\Dto\ValidationErrors;

class Form
{
    /**
     * @var array<string, array{resolver: ValueResolver, callback: callable(ValueResolver): ValueResolver}>
     */
    protected array $fields = [];

    protected array $options = [];

    protected array $arguments = [];

    protected ValidationErrors $errors;

    protected bool $isInteractive;

    protected array $prompted = [];

    /**
     * @param  callable(ValueResolver): ValueResolver  $callback
     */
    public function prompt(string $key, ?callable $callback = null, ?string $optionOrArgKey = null): ValueResolver
    {
        if ($callback) {
            $this->define($key, $callback, $optionOrArgKey);
        }

        if (! in_array($key, $this->prompted)) {
            $this->prompted[] = $key;
        }

        $result = ($this->fields[$key]['callback'])($this->fields[$key]['resolver'])->errors($this->errors);
        $result->retrieve();

        return $result;
    }

    /**
     * @param  callable(ValueResolver): ValueResolver  $callback
     */
    public function define(string $key, callable $callback, ?string $optionOrArgKey = null): ValueResolver
    {
        $optionOrArgKey = $optionOrArgKey ?? str($key)->replace('_', '-')->toString();
        $argOrOptionValue = $this->options[$optionOrArgKey] ?? $this->arguments[$optionOrArgKey] ?? null;
        $resolutionType = array_key_exists($optionOrArgKey, $this->options) ? 'option' : 'argument';

        $this->fields[$key] ??= [
            'resolver' => new ValueResolver(
                $key,
                $optionOrArgKey,
                $this->isInteractive,
                $argOrOptionValue,
                $resolutionType,
            ),
            'callback' => $callback,
        ];

        if ($argOrOptionValue !== null && ! in_array($key, $this->prompted)) {
            $this->prompted[] = $key;
        }

        return $this->fields[$key]['resolver'];
    }

    public function isInteractive(bool $isInteractive): self
    {
        $this->isInteractive = $isInteractive;

        return $this;
    }

    public function errors(ValidationErrors $errors): self
    {
        $this->errors = $errors;

        return $this;
    }

    public function options($options): self
    {
        $this->options = $options;

        return $this;
    }

    public function mergeOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    public function arguments($arguments): self
    {
        $this->arguments = $arguments;

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (! array_key_exists($key, $this->fields)) {
            return $default;
        }

        return $this->fields[$key]['resolver']->value();
    }

    public function integer(string $key, ?int $default = null): ?int
    {
        $result = $this->get($key, $default);

        return ($result === null) ? null : (int) $result;
    }

    public function boolean(string $key, ?bool $default = null): ?bool
    {
        $result = $this->get($key, $default);

        return ($result === null) ? null : (bool) $result;
    }

    /**
     * @return array<string, ValueResolver>
     */
    public function defined(): array
    {
        return collect($this->fields)->pluck('resolver')->toArray();
    }

    /**
     * @return array<string, ValueResolver>
     */
    public function filled(): array
    {
        return collect($this->prompted)->mapWithKeys(fn (string $key) => [
            $key => $this->fields[$key]['resolver'],
        ])->toArray();
    }

    public function hasAnyValues(): bool
    {
        return count($this->filled()) > 0;
    }

    public function isEmpty(): bool
    {
        return ! $this->hasAnyValues();
    }

    public function clear(): self
    {
        $this->fields = [];
        $this->prompted = [];

        return $this;
    }
}
