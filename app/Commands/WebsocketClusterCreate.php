<?php

namespace App\Commands;

use App\Actions\CreateWebSocketCluster;
use App\Concerns\DeterminesDefaultRegion;
use App\Concerns\Validates;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

class WebsocketClusterCreate extends BaseCommand
{
    use DeterminesDefaultRegion;
    use Validates;

    protected $signature = 'websocket-cluster:create
                            {--name= : Cluster name}
                            {--region= : Region}
                            {--json : Output as JSON}';

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
            fn () => app(CreateWebSocketCluster::class)->run($this->client, $defaults),
        );

        $this->outputJsonIfWanted($cluster);

        success('WebSocket cluster created');

        outro("Cluster created: {$cluster->name}");
    }
}
