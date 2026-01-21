<?php

namespace App\Dto;

use Carbon\CarbonImmutable;

class Command
{
    public function __construct(
        public readonly string $id,
        public readonly string $command,
        public readonly string $status,
        public readonly ?string $output = null,
        public readonly ?int $exitCode = null,
        public readonly ?CarbonImmutable $startedAt = null,
        public readonly ?CarbonImmutable $finishedAt = null,
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?CarbonImmutable $updatedAt = null,
        public readonly ?string $environmentId = null,
        public readonly ?string $instanceId = null,
    ) {
        //
    }

    public static function fromApiResponse(array $response, ?array $item = null): self
    {
        $data = $item ?? $response['data'] ?? [];
        $included = $response['included'] ?? [];

        $attributes = $data['attributes'] ?? [];
        $relationships = $data['relationships'] ?? [];

        return new self(
            id: $data['id'],
            command: $attributes['command'] ?? '',
            status: $attributes['status'] ?? '',
            output: $attributes['output'] ?? null,
            exitCode: $attributes['exit_code'] ?? null,
            startedAt: isset($attributes['started_at']) ? CarbonImmutable::parse($attributes['started_at']) : null,
            finishedAt: isset($attributes['finished_at']) ? CarbonImmutable::parse($attributes['finished_at']) : null,
            createdAt: isset($attributes['created_at']) ? CarbonImmutable::parse($attributes['created_at']) : null,
            updatedAt: isset($attributes['updated_at']) ? CarbonImmutable::parse($attributes['updated_at']) : null,
            environmentId: $relationships['environment']['data']['id'] ?? null,
            instanceId: $relationships['instance']['data']['id'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'command' => $this->command,
            'status' => $this->status,
            'output' => $this->output,
            'exit_code' => $this->exitCode,
            'started_at' => $this->startedAt?->toIso8601String(),
            'finished_at' => $this->finishedAt?->toIso8601String(),
            'created_at' => $this->createdAt?->toIso8601String(),
            'updated_at' => $this->updatedAt?->toIso8601String(),
            'environment_id' => $this->environmentId,
            'instance_id' => $this->instanceId,
        ];
    }
}
