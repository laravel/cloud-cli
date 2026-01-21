<?php

namespace App\Dto;

class ConfigSchema extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $required,
        public readonly ?bool $nullable = null,
        public readonly ?string $description = null,
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?array $enum = null,
        public readonly ?string $example = null,
    ) {
        //
    }

    public static function fromApiResponse(array $data): self
    {
        return new self(
            name: $data['name'],
            type: $data['type'],
            required: $data['required'],
            nullable: $data['nullable'] ?? null,
            description: $data['description'] ?? null,
            min: $data['min'] ?? null,
            max: $data['max'] ?? null,
            enum: $data['enum'] ?? null,
            example: $data['example'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'required' => $this->required,
            'nullable' => $this->nullable,
            'description' => $this->description,
            'min' => $this->min,
            'max' => $this->max,
            'enum' => $this->enum,
            'example' => $this->example,
        ];
    }
}
