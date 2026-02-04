<?php

namespace App\Resolvers;

use App\Dto\WebsocketApplication;
use App\Dto\WebsocketCluster;
use Illuminate\Support\Collection;

use function Laravel\Prompts\spin;

class WebSocketApplicationResolver extends Resolver
{
    public function from(WebsocketCluster $cluster, ?string $idOrName = null): ?WebsocketApplication
    {
        $app = ($idOrName ? $this->fromIdentifier($cluster, $idOrName) : null)
            ?? $this->fromInput($cluster);

        if (! $app) {
            $this->failAndExit('Unable to resolve WebSocket application: '.($idOrName ?? 'Provide a valid application ID or name as an argument.'));
        }

        $this->displayResolved('WebSocket application', $app->name, $app->id);

        return $app;
    }

    public function fromIdentifier(WebsocketCluster $cluster, string $identifier): ?WebsocketApplication
    {
        $apps = $this->fetchAll($cluster);

        return $apps->firstWhere('id', $identifier)
            ?? $apps->firstWhere('name', $identifier);
    }

    public function fromInput(WebsocketCluster $cluster): ?WebsocketApplication
    {
        $apps = $this->fetchAll($cluster);

        if ($apps->isEmpty()) {
            $this->failAndExit('No WebSocket applications found for this cluster.');
        }

        if ($apps->hasSole()) {
            return $apps->first();
        }

        $this->ensureInteractive('Please provide a WebSocket application ID or name.');

        $selected = selectWithContext(
            label: 'WebSocket application',
            options: $apps->mapWithKeys(fn (WebsocketApplication $a) => [$a->id => $a->name])->toArray(),
        );

        $this->displayResolved = false;

        return $apps->firstWhere('id', $selected);
    }

    protected function fetchAll(WebsocketCluster $cluster): Collection
    {
        return collect(spin(
            fn () => $this->client->websocketApplications()->list($cluster->id),
            'Fetching WebSocket applications...',
        ));
    }

    protected function idPrefix(): string
    {
        return 'wsa-';
    }
}
