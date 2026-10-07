<?php

namespace App\Dto;

use Carbon\CarbonInterval;
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

    public static function fromApiResponse(array $step): self
    {
        return new self(
            step: $step['step'],
            status: $step['status'],
            description: $step['description'],
            output: $step['output'] ?? null,
            durationMs: $step['duration_ms'] ?? null,
            time: $step['time'] ?? null,
        );
    }

    public function formattedDuration(): ?string
    {
        // The API occasionally reports a negative duration, which means it doesn't know.
        if ($this->durationMs === null || $this->durationMs < 0) {
            return null;
        }

        if ($this->durationMs < 1000) {
            return "{$this->durationMs}ms";
        }

        return CarbonInterval::milliseconds($this->durationMs)->cascade()->forHumans(['short' => true]);
    }
}
