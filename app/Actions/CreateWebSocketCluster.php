<?php

namespace App\Actions;

use App\Client\Connector;
use App\Dto\Region;
use App\Dto\WebsocketCluster;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class CreateWebSocketCluster
{
    public function run(Connector $client, array $defaults = []): WebsocketCluster
    {
        $name = text(
            label: 'Cluster name',
            default: $defaults['name'] ?? '',
            required: true,
        );

        $regions = spin(
            fn () => $client->meta()->regions(),
            'Fetching regions...',
        );

        $region = select(
            label: 'Region',
            options: collect($regions)->mapWithKeys(fn (Region $r) => [$r->value => $r->label])->toArray(),
            default: $defaults['region'] ?? null,
            required: true,
        );

        return spin(
            fn () => $client->websocketClusters()->create($name, $region, []),
            'Creating WebSocket cluster...',
        );
    }
}
