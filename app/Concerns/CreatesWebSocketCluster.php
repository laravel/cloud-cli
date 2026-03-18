<?php

namespace App\Concerns;

use App\Client\Requests\CreateWebSocketClusterRequestData;
use App\Dto\Region;
use App\Dto\WebsocketCluster;
use App\Enums\WebsocketServerMaxConnection;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

trait CreatesWebSocketCluster
{
    protected function createWebSocketCluster(array $defaults = []): WebsocketCluster
    {
        $this->form()->prompt(
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

        $this->form()->prompt(
            'region',
            fn ($resolver) => $resolver
                ->fromInput(
                    fn (?string $value) => select(
                        label: 'Region',
                        options: collect($regions)->mapWithKeys(fn (Region $r) => [$r->value => $r->label])->toArray(),
                        default: $value ?? $defaults['region'] ?? null,
                        required: true,
                    ),
                )
                ->nonInteractively(fn () => $defaults['region'] ?? null),
        );

        $this->form()->prompt(
            'max_connections',
            fn ($resolver) => $resolver
                ->fromInput(
                    fn ($value) => select(
                        label: 'Max connections',
                        options: collect(WebsocketServerMaxConnection::cases())->mapWithKeys(fn ($case) => [$case->value => $case->value])->toArray(),
                        default: $value ?? $defaults['max_connections'] ?? WebsocketServerMaxConnection::ONE_HUNDRED->value,
                        required: true,
                    ),
                )
                ->nonInteractively(fn () => $defaults['max_connections'] ?? WebsocketServerMaxConnection::ONE_HUNDRED->value),
        );

        return spin(
            fn () => $this->client->websocketClusters()->create(
                new CreateWebSocketClusterRequestData(
                    name: $this->form()->get('name'),
                    region: $this->form()->get('region'),
                    maxConnections: $this->form()->integer('max_connections'),
                ),
            ),
            'Creating WebSocket cluster...',
        );
    }

    protected function createWebSocketClusterWithOptions(array $options): WebsocketCluster
    {
        $name = $options['name'] ?? '';
        $region = $options['region'] ?? null;
        $maxConnections = (int) ($options['max_connections'] ?? WebsocketServerMaxConnection::ONE_HUNDRED->value);

        return spin(
            fn () => $this->client->websocketClusters()->create(
                new CreateWebSocketClusterRequestData(
                    name: $name,
                    region: $region,
                    maxConnections: $maxConnections,
                ),
            ),
            'Creating WebSocket cluster...',
        );
    }
}
