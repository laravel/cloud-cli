<?php

namespace App\Support;

use Laravel\Prompts\Concerns\Colors;

class KeyBindingsHelp
{
    use Colors;

    protected array $bindings = [];

    public function add(string $key, string $description): void
    {
        $this->bindings[$key] = $description;
    }

    public function get(): array
    {
        return [
            collect($this->bindings)->map(
                fn ($description, $key) => $this->bold($this->translateKey($key)).' '.$this->dim($description),
            )->join(str_repeat(' ', 4)),
        ];
    }

    public function clear(): void
    {
        $this->bindings = [];
    }

    protected function translateKey(string $key): string
    {
        return match ($key) {
            "\n" => 'Enter',
            default => $key,
        };
    }
}
