<?php

namespace App\Resolvers;

use App\Dto\WebsocketApplication;
use App\Dto\WebsocketCluster;
use App\Resolvers\Concerns\HasWebSocketCluster;
use Illuminate\Support\LazyCollection;

use function Laravel\Prompts\spin;

class WebSocketApplicationResolver extends Resolver
{
    use HasWebSocketCluster;

    public function resolve(): ?WebsocketApplication
    {
        return $this->from();
    }

    public function from(?string $idOrName = null): ?WebsocketApplication
    {
        $app = ($idOrName ? $this->fromIdentifier($idOrName) : null)
            ?? $this->fromInput();

        if (! $app) {
            if ($idOrName === null) {
                $this->failAndExit('No WebSocket application could be resolved. Provide a valid application ID or name as an argument.');
            } elseif ($this->looksLikeId($idOrName)) {
                $this->failAndExit("WebSocket application '{$idOrName}' not found. Verify the ID is correct and belongs to your cluster.");
            } else {
                $this->failAndExit("No WebSocket application named '{$idOrName}' found for this cluster.");
            }
        }

        $this->displayResolved('WebSocket application', $app->name, $app->id);

        return $app;
    }

    public function fromIdentifier(string $identifier): ?WebsocketApplication
    {
        return $this->resolveFromIdentifier(
            $identifier,
            fn () => spin(
                fn () => $this->client->websocketApplications()->get($identifier),
                'Fetching WebSocket application...',
            ),
            fn () => $this->resolveFromCluster($identifier),
        );
    }

    protected function resolveFromCluster(string $identifier): ?WebsocketApplication
    {
        $apps = $this->fetchAll($this->cluster());

        return $apps->firstWhere('id', $identifier)
            ?? $apps->firstWhere('name', $identifier);
    }

    public function fromInput(): ?WebsocketApplication
    {
        $apps = $this->fetchAll($this->cluster());

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

    protected function fetchAll(WebsocketCluster $cluster): LazyCollection
    {
        return spin(
            fn () => $this->client->websocketApplications()->list($cluster->id)->collect(),
            'Fetching WebSocket applications...',
        );
    }

    protected function idPrefix(): string
    {
        return 'wsa-';
    }
}
