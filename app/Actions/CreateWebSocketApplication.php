<?php

namespace App\Actions;

use App\Client\Connector;
use App\Dto\WebsocketApplication;
use App\Dto\WebsocketCluster;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class CreateWebSocketApplication
{
    public function run(Connector $client, WebsocketCluster $cluster, array $defaults = []): WebsocketApplication
    {
        $name = text(
            label: 'Application name',
            default: $defaults['name'] ?? '',
            required: true,
        );

        return spin(
            fn () => $client->websocketApplications()->create($cluster->id, ['name' => $name]),
            'Creating WebSocket application...',
        );
    }
}
