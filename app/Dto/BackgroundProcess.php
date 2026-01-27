<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

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
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $updatedAt = null,
        public readonly ?string $instanceId = null,
    ) {
        //
    }

    public static function fromJsonApi(array $response): self
    {
        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];
        $relationships = $data['relationships'] ?? [];

        $transformed = [
            'id' => $data['id'],
            'command' => $attributes['command'] ?? '',
            'instances' => $attributes['instances'] ?? 1,
            'type' => $attributes['type'] ?? '',
            'queue' => $attributes['queue'] ?? null,
            'connection' => $attributes['connection'] ?? null,
            'timeout' => $attributes['timeout'] ?? null,
            'sleep' => $attributes['sleep'] ?? null,
            'tries' => $attributes['tries'] ?? null,
            'maxProcesses' => $attributes['max_processes'] ?? null,
            'minProcesses' => $attributes['min_processes'] ?? null,
            'createdAt' => $attributes['created_at'] ?? null,
            'updatedAt' => $attributes['updated_at'] ?? null,
        ];

        if (isset($relationships['instance']['data']['id'])) {
            $transformed['instanceId'] = $relationships['instance']['data']['id'];
        }

        return self::from($transformed);
    }
}
