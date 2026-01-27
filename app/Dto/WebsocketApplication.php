<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class WebsocketApplication extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $appId,
        public readonly array $allowedOrigins,
        public readonly int $pingInterval,
        public readonly int $activityTimeout,
        public readonly int $maxMessageSize,
        public readonly int $maxConnections,
        public readonly string $key,
        public readonly string $secret,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?string $serverId = null,
        public readonly ?WebsocketCluster $server = null,
    ) {
        //
    }

    public static function fromJsonApi(array $response): self
    {
        $data = $response['data'] ?? [];
        $included = $response['included'] ?? [];
        $attributes = $data['attributes'] ?? [];
        $relationships = $data['relationships'] ?? [];

        $transformed = [
            'id' => $data['id'],
            'name' => $attributes['name'],
            'appId' => $attributes['app_id'],
            'allowedOrigins' => $attributes['allowed_origins'] ?? [],
            'pingInterval' => $attributes['ping_interval'],
            'activityTimeout' => $attributes['activity_timeout'],
            'maxMessageSize' => $attributes['max_message_size'],
            'maxConnections' => $attributes['max_connections'],
            'key' => $attributes['key'],
            'secret' => $attributes['secret'],
            'createdAt' => $attributes['created_at'] ?? null,
        ];

        if (isset($relationships['server']['data']['id'])) {
            $transformed['serverId'] = $relationships['server']['data']['id'];
            $serverData = self::resolveIncluded($included, $relationships['server'], 'websocketServers');
            if ($serverData) {
                $transformed['server'] = WebsocketCluster::fromJsonApi(['data' => $serverData, 'included' => $included])->toArray();
            }
        }

        return self::from($transformed);
    }

    protected static function resolveIncluded(array $included, ?array $relationship, string $type): ?array
    {
        if (! $relationship || ! isset($relationship['data']['id'])) {
            return null;
        }

        $id = $relationship['data']['id'];

        return collect($included)
            ->first(fn ($item) => $item['type'] === $type && $item['id'] === $id);
    }
}
