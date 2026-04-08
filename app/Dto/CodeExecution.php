<?php

namespace App\Dto;

use App\Enums\CommandStatus;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class CodeExecution extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        #[WithCast(EnumCast::class)]
        public readonly CommandStatus $status,
        public readonly ?string $output = null,
        public readonly ?int $exitCode = null,
        public readonly ?string $failureReason = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $startedAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $finishedAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];

        return self::from([
            'id' => $data['id'],
            'code' => $attributes['code'] ?? '',
            'status' => $attributes['status'] ?? '',
            'output' => $attributes['output'] ?? null,
            'exitCode' => $attributes['exit_code'] ?? null,
            'failureReason' => $attributes['failure_reason'] ?? null,
            'startedAt' => $attributes['started_at'] ?? null,
            'finishedAt' => $attributes['finished_at'] ?? null,
            'createdAt' => $attributes['created_at'] ?? null,
        ]);
    }
}
