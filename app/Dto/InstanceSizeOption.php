<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class InstanceSizeOption extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $description,
        public readonly string $cpuType,
        public readonly string $computeClass,
        public readonly int $cpuCount,
        public readonly int $memoryMib,
    ) {
        //
    }

    public static function fromApiResponse(array $item): self
    {
        return new self(
            name: $item['name'],
            label: $item['label'],
            description: $item['description'],
            cpuType: $item['cpu_type'],
            computeClass: $item['compute_class'] ?? $item['computeClass'],
            cpuCount: $item['cpu_count'] ?? $item['cpuCount'],
            memoryMib: $item['memory_mib'] ?? $item['memoryMib'],
        );
    }
}
