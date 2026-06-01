<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class ManagedQueueFailedJob extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $queue,
        public readonly int $attempts,
        public readonly ?string $exception = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class, format: 'Y-m-d H:i:s.u')]
        public readonly ?CarbonImmutable $failedAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class, format: 'Y-m-d H:i:s.u')]
        public readonly ?CarbonImmutable $startedAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class, format: 'Y-m-d H:i:s.u')]
        public readonly ?CarbonImmutable $retriedAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class, format: 'Y-m-d H:i:s.u')]
        public readonly ?CarbonImmutable $retryReservedUntil = null,
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? $response;
        $attributes = $data['attributes'] ?? [];

        return self::from([
            'id' => $data['id'],
            'queue' => $attributes['queue'],
            'attempts' => (int) ($attributes['attempts'] ?? 0),
            'exception' => $attributes['exception'] ?: null,
            'failedAt' => $attributes['failed_at'] ?: null,
            'startedAt' => $attributes['started_at'] ?: null,
            'retriedAt' => $attributes['retried_at'] ?: null,
            'retryReservedUntil' => $attributes['retry_reserved_until'] ?: null,
        ]);
    }
}
