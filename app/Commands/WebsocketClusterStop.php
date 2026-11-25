<?php

namespace App\Commands;

use App\Dto\WebsocketCluster;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class WebsocketClusterStop extends BaseCommand
{
    protected ?string $jsonDataClass = WebsocketCluster::class;

    protected $signature = 'websocket-cluster:stop
                            {cluster? : The cluster ID or name}
                            {--force : Skip confirmation}';

    protected $description = 'Stop a WebSocket cluster';

    protected $aliases = ['ws-cluster:stop'];

    public function handle()
    {
        $this->ensureClient();

        intro('Stopping WebSocket Cluster');

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));

        $this->confirmDestructive("Stop WebSocket cluster '{$cluster->name}'?");

        $stopped = spin(
            fn () => $this->client->websocketClusters()->stop($cluster->id),
            'Stopping WebSocket cluster...',
        );

        $this->outputJsonIfWanted($stopped);

        success("WebSocket cluster '{$stopped->name}' stopped.");
    }
}
