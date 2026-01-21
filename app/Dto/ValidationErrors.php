<?php

namespace App\Dto;

class ValidationErrors
{
    protected array $errors = [];

    public function add(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    public function has(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    public function messageContains(string $field, string $needle): bool
    {
        return isset($this->errors[$field]) && str_contains($this->errors[$field], $needle);
    }

    public function clear(): void
    {
        $this->errors = [];
    }
}
