<?php

namespace App\Concerns;

use App\Client\Requests\CreateWebSocketClusterRequestData;
use App\Dto\Region;
use App\Dto\WebsocketCluster;

use function Laravel\Prompts\number;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

trait CreatesWebSocketCluster
{
    protected function createWebSocketCluster(array $defaults = []): WebsocketCluster
    {
        $this->fields()->add(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Cluster name',
                    default: $value ?? $defaults['name'] ?? '',
                    required: true,
                ),
            ),
        );

        $regions = spin(
            fn () => $this->client->meta()->regions(),
            'Fetching regions...',
        );

        $this->fields()->add(
            'region',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => select(
                    label: 'Region',
                    options: collect($regions)->mapWithKeys(fn (Region $r) => [$r->value => $r->label])->toArray(),
                    default: $value ?? $defaults['region'] ?? null,
                    required: true,
                ),
            ),
        );

        $this->fields()->add(
            'max_connections',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'Max connections',
                    default: $value ?? $defaults['max_connections'] ?? 100,
                    required: true,
                ),
            ),
        );

        return spin(
            fn () => $this->client->websocketClusters()->create(new CreateWebSocketClusterRequestData(
                name: $this->fields()->get('name'),
                region: $this->fields()->get('region'),
                maxConnections: (int) $this->fields()->get('max_connections'),
            )),
            'Creating WebSocket cluster...',
        );
    }
}
