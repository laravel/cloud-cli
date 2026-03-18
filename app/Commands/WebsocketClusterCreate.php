<?php

namespace App\Commands;

use App\Concerns\CreatesWebSocketCluster;
use App\Concerns\DeterminesDefaultRegion;

use function Laravel\Prompts\intro;

class WebsocketClusterCreate extends BaseCommand
{
    use CreatesWebSocketCluster;
    use DeterminesDefaultRegion;

    protected $signature = 'websocket-cluster:create
                            {--name= : Cluster name}
                            {--region= : Region}
                            {--max-connections= : Max connections (100, 200, 500, 2000, 5000, 10000)}
                            {--json : Output as JSON}';

    protected $description = 'Create a WebSocket cluster';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating WebSocket Cluster');

        $defaults = array_filter([
            'name' => $this->option('name'),
            'region' => $this->option('region') ?: $this->getDefaultRegion(),
            'max_connections' => $this->option('max-connections'),
        ]);

        $cluster = $this->loopUntilValid(
            fn () => $this->createWebSocketCluster($defaults),
        );

        $this->outputJsonIfWanted($cluster);

        success("WebSocket cluster created: {$cluster->name}");
    }
}
