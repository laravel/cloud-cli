<?php

namespace App\Support;

class UpdateField
{
    protected mixed $currentValue = null;

    protected ?string $label = null;

    protected ?string $dataKey = null;

    public function __construct(
        public readonly string $key,
        public readonly mixed $prompt,
    ) {
        //
    }

    public function currentValue(mixed $value): self
    {
        $this->currentValue = $value;

        return $this;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function dataKey(string $key): self
    {
        $this->dataKey = $key;

        return $this;
    }

    public function get()
    {
        return [
            'key' => $this->dataKey ?? $this->key,
            'label' => $this->label ?? str($this->key)->replace('-', ' ')->ucfirst()->toString(),
            'prompt' => $this->prompt,
            'current' => $this->currentValue,
        ];
    }
}
