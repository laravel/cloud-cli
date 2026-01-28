<?php

namespace App\Support;

class UpdateFields
{
    /**
     * @var array<string, array{key: string, label: string, prompt: callable}>
     */
    protected array $fields = [];

    public function add(string $key, callable $prompt): UpdateField
    {
        $field = new UpdateField($key, $prompt);

        $this->fields[$key] = $field;

        return $field;
    }

    public function get(): array
    {
        return collect($this->fields)->mapWithKeys(fn (UpdateField $field) => [
            $field->key => $field->get(),
        ])->toArray();
    }
}
