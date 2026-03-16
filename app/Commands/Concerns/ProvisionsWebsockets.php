<?php

namespace App\Commands\Concerns;

use App\Dto\WebsocketApplication;
use App\Dto\WebsocketCluster;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

trait ProvisionsWebsockets
{
    protected function provisionWebsocketOpinionated(): ?string
    {
        $defaults = $this->getWebSocketClusterDefaults();
        $defaults['max_connections'] = $defaults['max_connections'] ?? 100;

        $clusters = $this->client->websocketClusters()->list()->collect();

        $cluster = $clusters->isEmpty()
            ? $this->createWebSocketClusterWithOptions($defaults)
            : $clusters->first();

        $appName = $this->appName ? str($this->appName)->snake()->replace('-', '_')->toString() : 'websocket';

        $websocketApp = $this->createWebSocketApplicationWithOptions($cluster, array_merge($defaults, ['name' => $appName]));

        return $websocketApp->id;
    }

    protected function getWebSocketApplication(WebsocketCluster $cluster): ?WebsocketApplication
    {
        $applicationsPaginator = $this->client->websocketApplications()->list($cluster->id);
        $applications = $applicationsPaginator->collect();

        if ($applications->isEmpty()) {
            return null;
        }

        $options = $applications->collect()->mapWithKeys(fn (WebsocketApplication $application) => [$application->id => $application->name]);
        $options->prepend('Create new websocket application', 'new');

        $application = select(
            label: 'Websocket application',
            options: $options->toArray(),
        );

        if ($application !== 'new') {
            return $applications->firstWhere('id', $application);
        }

        return $this->loopUntilValid(
            fn () => $this->createWebSocketApplication($cluster, []),
        );
    }

    protected function getWebsocketCluster(): ?WebsocketCluster
    {
        $clustersPaginator = $this->client->websocketClusters()->list();
        $clusters = $clustersPaginator->collect();

        if ($clusters->isEmpty()) {
            warning('No websocket clusters found.');

            $createWebsocketCluster = confirm('Do you want to create a new websocket cluster?');

            if ($createWebsocketCluster) {
                return $this->loopUntilValid(
                    fn () => $this->createWebSocketCluster(),
                );
            }

            return null;
        }

        $options = $clusters->collect()->mapWithKeys(fn (WebsocketCluster $cluster) => [$cluster->id => $cluster->name]);
        $options->prepend('Create new websocket cluster', 'new');

        $cluster = select(
            label: 'Websocket cluster',
            options: $options->toArray(),
            default: $clusters->first()->id ?? null,
            required: true,
        );

        if ($cluster !== 'new') {
            return $clusters->firstWhere('id', $cluster);
        }

        return $this->loopUntilValid(
            fn () => $this->createWebSocketCluster($this->getWebSocketClusterDefaults()),
        );
    }

    protected function getWebSocketClusterDefaults(): array
    {
        return array_filter([
            'name' => $this->appName ? str($this->appName)->snake()->replace('-', '_')->toString() : null,
            'region' => $this->region,
        ]);
    }
}
