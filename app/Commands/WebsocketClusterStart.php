<?php

namespace App\Commands;

use App\Dto\WebsocketCluster;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class WebsocketClusterStart extends BaseCommand
{
    protected ?string $jsonDataClass = WebsocketCluster::class;

    protected $signature = 'websocket-cluster:start {cluster? : The cluster ID or name}';

    protected $description = 'Start a stopped WebSocket cluster';

    protected $aliases = ['ws-cluster:start'];

    public function handle()
    {
        $this->ensureClient();

        intro('Starting WebSocket Cluster');

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));

        $started = spin(
            fn () => $this->client->websocketClusters()->start($cluster->id),
            'Starting WebSocket cluster...',
        );

        $this->outputJsonIfWanted($started);

        success("WebSocket cluster '{$started->name}' started.");
    }
}
