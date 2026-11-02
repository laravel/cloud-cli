<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class ScheduledCommand extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $command,
        public readonly string $schedule,
        public readonly ?string $name = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?string $instanceId = null,
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];

        return self::from([
            'id' => $data['id'],
            'command' => $attributes['command'],
            'schedule' => $attributes['schedule'],
            'name' => $attributes['name'] ?? null,
            'createdAt' => $attributes['created_at'] ?? null,
            'instanceId' => $data['relationships']['instance']['data']['id'] ?? null,
        ]);
    }
}
