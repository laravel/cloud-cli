<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class Command extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $command,
        public readonly string $status,
        public readonly ?string $output = null,
        public readonly ?int $exitCode = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $startedAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $finishedAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $updatedAt = null,
        public readonly ?string $environmentId = null,
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
            'status' => $attributes['status'] ?? '',
            'output' => $attributes['output'] ?? null,
            'exitCode' => $attributes['exit_code'] ?? null,
            'startedAt' => $attributes['started_at'] ?? null,
            'finishedAt' => $attributes['finished_at'] ?? null,
            'createdAt' => $attributes['created_at'] ?? null,
            'updatedAt' => $attributes['updated_at'] ?? null,
        ];

        if (isset($relationships['environment']['data']['id'])) {
            $transformed['environmentId'] = $relationships['environment']['data']['id'];
        }

        if (isset($relationships['instance']['data']['id'])) {
            $transformed['instanceId'] = $relationships['instance']['data']['id'];
        }

        return self::from($transformed);
    }
}
