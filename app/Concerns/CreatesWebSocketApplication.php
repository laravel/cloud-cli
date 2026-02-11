<?php

namespace App\Concerns;

use App\Client\Requests\CreateWebSocketApplicationRequestData;
use App\Dto\WebsocketApplication;
use App\Dto\WebsocketCluster;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

trait CreatesWebSocketApplication
{
    protected function createWebSocketApplication(WebsocketCluster $cluster, array $defaults = []): WebsocketApplication
    {
        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Application name',
                    default: $value ?? $defaults['name'] ?? '',
                    required: true,
                ),
            ),
        );

        return spin(
            fn () => $this->client->websocketApplications()->create(new CreateWebSocketApplicationRequestData(
                clusterId: $cluster->id,
                name: $this->form()->get('name'),
            )),
            'Creating WebSocket application...',
        );
    }
}
