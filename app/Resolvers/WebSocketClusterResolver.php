<?php

namespace App\Resolvers;

use App\Dto\WebsocketCluster;
use Illuminate\Support\Collection;

use function Laravel\Prompts\spin;

class WebSocketClusterResolver extends Resolver
{
    public function resolve(): ?WebsocketCluster
    {
        return $this->from();
    }

    public function from(?string $idOrName = null): ?WebsocketCluster
    {
        $cluster = ($idOrName ? $this->fromIdentifier($idOrName) : null)
            ?? $this->fromInput();

        if (! $cluster) {
            $this->failAndExit('Unable to resolve WebSocket cluster: '.($idOrName ?? 'Provide a valid cluster ID or name as an argument.'));
        }

        $this->displayResolved('WebSocket cluster', $cluster->name, $cluster->id);

        return $cluster;
    }

    public function fromIdentifier(string $identifier): ?WebsocketCluster
    {
        return $this->resolveFromIdentifier(
            $identifier,
            fn () => spin(
                fn () => $this->client->websocketClusters()->get($identifier),
                'Fetching WebSocket cluster...',
            ),
            fn () => $this->fetchAndFind($identifier),
        );
    }

    public function fromInput(): ?WebsocketCluster
    {
        $clusters = $this->fetchAll();

        if ($clusters->isEmpty()) {
            $this->failAndExit('No WebSocket clusters found.');
        }

        if ($clusters->hasSole()) {
            return $clusters->first();
        }

        $this->ensureInteractive('Please provide a WebSocket cluster ID or name.');

        $selected = selectWithContext(
            label: 'WebSocket cluster',
            options: $clusters->mapWithKeys(fn (WebsocketCluster $c) => [$c->id => $c->name])->toArray(),
        );

        $this->displayResolved = false;

        return $clusters->firstWhere('id', $selected);
    }

    public function fetchAndFind(string $identifier): ?WebsocketCluster
    {
        return $this->fetchAll()->firstWhere('id', $identifier)
            ?? $this->fetchAll()->firstWhere('name', $identifier);
    }

    protected function fetchAll(): Collection
    {
        return collect(spin(
            fn () => $this->client->websocketClusters()->list(),
            'Fetching WebSocket clusters...',
        ));
    }

    protected function idPrefix(): string
    {
        return 'ws-';
    }
}
