<?php

namespace App\Commands;

use App\Concerns\CreatesWebSocketCluster;
use App\Concerns\DeterminesDefaultRegion;
use App\Dto\WebsocketCluster;

use function Laravel\Prompts\intro;

class WebsocketClusterCreate extends BaseCommand
{
    protected ?string $jsonDataClass = WebsocketCluster::class;

    use CreatesWebSocketCluster;
    use DeterminesDefaultRegion;

    protected $signature = 'websocket-cluster:create
                            {--name= : Cluster name}
                            {--region= : Region}';

    protected $description = 'Create a WebSocket cluster';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating WebSocket Cluster');

        $defaults = array_filter([
            'name' => $this->option('name'),
            'region' => $this->option('region') ?: $this->getDefaultRegion(),
        ]);

        $cluster = $this->loopUntilValid(
            fn () => $this->createWebSocketCluster($defaults),
        );

        $this->outputJsonIfWanted($cluster);

        success("WebSocket cluster created: {$cluster->name}");
    }
}
