<?php

namespace App\Dto;

use Carbon\CarbonImmutable;

class BackgroundProcess extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $command,
        public readonly int $instances,
        public readonly string $type,
        public readonly ?string $queue = null,
        public readonly ?string $connection = null,
        public readonly ?int $timeout = null,
        public readonly ?int $sleep = null,
        public readonly ?int $tries = null,
        public readonly ?int $maxProcesses = null,
        public readonly ?int $minProcesses = null,
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?CarbonImmutable $updatedAt = null,
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
            instances: $attributes['instances'] ?? 1,
            type: $attributes['type'] ?? '',
            queue: $attributes['queue'] ?? null,
            connection: $attributes['connection'] ?? null,
            timeout: $attributes['timeout'] ?? null,
            sleep: $attributes['sleep'] ?? null,
            tries: $attributes['tries'] ?? null,
            maxProcesses: $attributes['max_processes'] ?? null,
            minProcesses: $attributes['min_processes'] ?? null,
            createdAt: isset($attributes['created_at']) ? CarbonImmutable::parse($attributes['created_at']) : null,
            updatedAt: isset($attributes['updated_at']) ? CarbonImmutable::parse($attributes['updated_at']) : null,
            instanceId: $relationships['instance']['data']['id'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'command' => $this->command,
            'instances' => $this->instances,
            'type' => $this->type,
            'queue' => $this->queue,
            'connection' => $this->connection,
            'timeout' => $this->timeout,
            'sleep' => $this->sleep,
            'tries' => $this->tries,
            'max_processes' => $this->maxProcesses,
            'min_processes' => $this->minProcesses,
            'created_at' => $this->createdAt?->toIso8601String(),
            'updated_at' => $this->updatedAt?->toIso8601String(),
            'instance_id' => $this->instanceId,
        ];
    }
}
