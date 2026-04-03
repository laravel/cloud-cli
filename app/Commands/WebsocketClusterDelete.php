<?php

namespace App\Commands;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class WebsocketClusterDelete extends BaseCommand
{
    protected $signature = 'websocket-cluster:delete
                            {cluster? : The cluster ID or name}
                            {--force : Skip confirmation}';

    protected $description = 'Delete a WebSocket cluster';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting WebSocket Cluster');

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));

        if (! $this->option('force') && ! confirm("Delete WebSocket cluster '{$cluster->name}'?", default: false)) {
            error('Cancelled');

            return self::FAILURE;
        }

        spin(
            fn () => $this->client->websocketClusters()->delete($cluster->id),
            'Deleting WebSocket cluster...',
        );

        $this->outputJsonIfWanted('WebSocket cluster deleted.');

        success('WebSocket cluster deleted');
    }
}
