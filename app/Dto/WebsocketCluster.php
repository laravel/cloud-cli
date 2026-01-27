<?php

namespace App\Dto;

use App\Enums\WebsocketServerConnectionDistributionStrategy;
use App\Enums\WebsocketServerMaxConnection;
use App\Enums\WebsocketServerStatus;
use App\Enums\WebsocketServerType;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class WebsocketCluster extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        #[WithCast(EnumCast::class)]
        public readonly WebsocketServerType $type,
        public readonly string $region,
        #[WithCast(EnumCast::class)]
        public readonly WebsocketServerStatus $status,
        #[WithCast(EnumCast::class)]
        public readonly WebsocketServerMaxConnection $maxConnections,
        #[WithCast(EnumCast::class)]
        public readonly WebsocketServerConnectionDistributionStrategy $connectionDistributionStrategy,
        public readonly string $hostname,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly array $applicationIds = [],
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
            'name' => $attributes['name'],
            'type' => $attributes['type'],
            'region' => $attributes['region'],
            'status' => $attributes['status'],
            'maxConnections' => $attributes['max_connections'],
            'connectionDistributionStrategy' => $attributes['connection_distribution_strategy'],
            'hostname' => $attributes['hostname'],
            'createdAt' => $attributes['created_at'] ?? null,
        ];

        if (isset($relationships['applications']['data'])) {
            $transformed['applicationIds'] = array_column($relationships['applications']['data'], 'id');
        }

        return self::from($transformed);
    }
}
