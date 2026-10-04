<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class DeploymentLogStep extends Data
{
    public function __construct(
        public readonly string $step,
        public readonly string $status,
        public readonly string $description,
        public readonly ?string $output = null,
        public readonly ?int $durationMs = null,
        public readonly ?string $time = null,
    ) {
        //
    }

    public function failed(): bool
    {
        return $this->status === 'failed';
    }

    public static function createFromResponse(array $data): self
    {
        return new self(
            step: (string) ($data['step'] ?? ''),
            status: (string) ($data['status'] ?? 'pending'),
            description: (string) ($data['description'] ?? $data['step'] ?? ''),
            output: isset($data['output']) ? (string) $data['output'] : null,
            durationMs: isset($data['duration_ms']) ? (int) $data['duration_ms'] : null,
            time: $data['time'] ?? null,
        );
    }
}
